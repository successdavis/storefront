<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Customers\BulkCustomerActionRequest;
use App\Http\Requests\Admin\Customers\SendCustomerEmailRequest;
use App\Http\Requests\Admin\Customers\UpdateCustomerRequest;
use App\Http\Requests\Admin\Customers\UpdateCustomerStatusRequest;
use App\Models\User;
use App\Services\CustomerManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminCustomerController extends Controller
{
    public function __construct(
        protected CustomerManagementService $customerManagementService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = $this->validatedFilters($request);
        $customers = $this->customerManagementService->listCustomers($filters);

        return Inertia::render('Admin/Customers/Index', [
            'filters' => $filters,
            'summary_cards' => $this->customerManagementService->summaryCards($filters),
            'filter_options' => $this->customerManagementService->filterOptions(),
            'bulk_actions' => $this->customerManagementService->bulkActions(),
            'customers' => $customers->through(
                fn (User $customer) => $this->customerManagementService->toListPayload($customer)
            ),
        ]);
    }

    public function show(User $customer): Response
    {
        $this->authorize('view', $customer);

        return Inertia::render('Admin/Customers/Show', [
            'customer' => $this->customerManagementService->detailPayload($customer),
            'permissions' => [
                'can_update' => request()->user()->can('update', $customer),
                'can_change_status' => request()->user()->can('changeStatus', $customer),
                'can_send_email' => request()->user()->can('sendEmail', $customer),
                'can_manage_notes' => request()->user()->can('manageNotes', $customer),
                'can_export' => request()->user()->can('export', User::class),
            ],
        ]);
    }

    public function update(UpdateCustomerRequest $request, User $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $this->customerManagementService->updateCustomer(
            $customer,
            $request->validated(),
            $request->user(),
        );

        return back()->with('success', 'Customer profile updated.');
    }

    public function updateStatus(UpdateCustomerStatusRequest $request, User $customer): RedirectResponse
    {
        $this->authorize('changeStatus', $customer);

        $this->customerManagementService->changeStatus(
            $customer,
            (string) $request->validated('status'),
            $request->user(),
            $request->validated('note'),
        );

        return back()->with('success', 'Customer status updated.');
    }

    public function markVerified(Request $request, User $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $this->customerManagementService->markEmailVerified($customer, $request->user());

        return back()->with('success', 'Customer email marked as verified.');
    }

    public function resendVerification(Request $request, User $customer): RedirectResponse
    {
        $this->authorize('sendEmail', $customer);

        $this->customerManagementService->resendVerificationEmail($customer, $request->user());

        return back()->with('success', 'Verification email queued for delivery.');
    }

    public function sendPasswordReset(Request $request, User $customer): RedirectResponse
    {
        $this->authorize('sendEmail', $customer);

        $this->customerManagementService->sendPasswordReset($customer, $request->user());

        return back()->with('success', 'Password reset email queued for delivery.');
    }

    public function sendEmail(SendCustomerEmailRequest $request, User $customer): RedirectResponse
    {
        $this->authorize('sendEmail', $customer);

        $this->customerManagementService->sendEmail(
            $customer,
            $request->user(),
            (string) $request->validated('subject'),
            (string) $request->validated('message'),
        );

        return back()->with('success', 'Customer email queued for delivery.');
    }

    public function bulk(BulkCustomerActionRequest $request): RedirectResponse
    {
        $this->authorize('bulkAction', User::class);

        $result = $this->customerManagementService->bulkAction(
            customerIds: $request->validated('customer_ids'),
            action: (string) $request->validated('action'),
            actor: $request->user(),
        );

        $failedCount = count($result['failed']);
        $message = $result['success_count'] . ' customer(s) updated.';

        if ($failedCount > 0) {
            $message .= ' ' . $failedCount . ' customer(s) could not be updated.';
        }

        return back()->with($failedCount > 0 ? 'warning' : 'success', $message);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', User::class);

        $filters = $this->validatedFilters($request, includePerPage: false);
        $selectedIds = $this->selectedCustomerIds($request);
        $rows = $this->customerManagementService->exportRows($filters, $selectedIds);

        $filename = sprintf('customers-%s.csv', now()->format('YmdHis'));

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'ID',
                'Name',
                'Email',
                'Phone',
                'Status',
                'Email Verified',
                'Registered At',
                'Last Login At',
                'Total Orders',
                'Total Spend',
                'Last Order At',
                'VIP',
                'Risk Flagged',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['id'],
                    $row['name'],
                    $row['email'],
                    $row['phone'],
                    $row['status'],
                    $row['email_verified'],
                    $row['registered_at'],
                    $row['last_login_at'],
                    $row['total_orders'],
                    $row['total_spend'],
                    $row['last_order_at'],
                    $row['vip'],
                    $row['risky'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function validatedFilters(Request $request, bool $includePerPage = true): array
    {
        $rules = [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
            'email_verified' => ['nullable', 'in:verified,unverified'],
            'has_orders' => ['nullable', 'in:with_orders,without_orders'],
            'registered_from' => ['nullable', 'date'],
            'registered_to' => ['nullable', 'date'],
            'last_login_from' => ['nullable', 'date'],
            'last_login_to' => ['nullable', 'date'],
            'high_value' => ['nullable', 'boolean'],
            'dormant_days' => ['nullable', 'in:30,60,90,180'],
            'sort' => ['nullable', 'in:newest,oldest,name_asc,total_orders_desc,total_spend_desc,last_login_desc,last_order_desc'],
        ];

        if ($includePerPage) {
            $rules['per_page'] = ['nullable', 'integer', 'min:10', 'max:100'];
        }

        return $request->validate($rules);
    }

    protected function selectedCustomerIds(Request $request): ?array
    {
        $ids = $request->query('ids');

        if ($ids === null || $ids === '') {
            return null;
        }

        $values = is_array($ids) ? $ids : explode(',', (string) $ids);

        return collect($values)
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
    }
}
