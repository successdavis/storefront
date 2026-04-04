<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Orders\BulkOrderActionRequest;
use App\Http\Requests\Admin\Orders\StoreOrderNoteRequest;
use App\Http\Requests\Admin\Orders\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminOrderController extends Controller
{
    public function __construct(protected OrderManagementService $orderManagementService) {}

    public function index(Request $request): Response
    {
        $this->authorize('manageAny', Order::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'order_number' => ['nullable', 'string', 'max:120'],
            'customer' => ['nullable', 'string', 'max:120'],
            'payment_status' => ['nullable', 'string', 'max:40'],
            'order_status' => ['nullable', 'string', 'max:40'],
            'fulfillment_status' => ['nullable', 'string', 'max:40'],
            'channel' => ['nullable', 'string', 'max:40'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', 'max:40'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $orders = $this->orderManagementService->listOrders($filters);

        return Inertia::render('Admin/Orders/Index', [
            'filters' => $filters,
            'summary_cards' => $this->orderManagementService->summaryCards($filters),
            'filter_options' => $this->orderManagementService->filterOptions(),
            'orders' => $orders->through(fn (Order $order) => $this->orderManagementService->toAdminListPayload($order)),
            'bulk_actions' => [
                ['value' => OrderManagementService::ACTION_MARK_PROCESSING, 'label' => 'Mark processing'],
                ['value' => OrderManagementService::ACTION_MARK_PACKED, 'label' => 'Mark packed'],
            ],
        ]);
    }

    public function show(Order $order): Response
    {
        $this->authorize('manage', $order);

        return Inertia::render('Admin/Orders/Show', [
            'order' => $this->orderManagementService->adminDetailPayload($order),
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('manage', $order);

        $this->orderManagementService->performAction($order, (string) $request->validated('action'), $request->validated(), $request->user());

        return back()->with('success', 'Order updated successfully.');
    }

    public function storeNote(StoreOrderNoteRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('manage', $order);

        $this->orderManagementService->storeInternalNote($order, $request->user(), (string) $request->validated('note'));

        return back()->with('success', 'Internal note added.');
    }

    public function bulkUpdate(BulkOrderActionRequest $request): RedirectResponse
    {
        $this->authorize('manageAny', Order::class);

        $result = $this->orderManagementService->bulkAction(
            orderIds: $request->validated('order_ids'),
            action: (string) $request->validated('action'),
            payload: $request->validated(),
            actor: $request->user(),
        );

        $failedCount = count($result['failed']);
        $message = $result['success_count'] . ' order(s) updated.';
        if ($failedCount > 0) {
            $message .= ' ' . $failedCount . ' order(s) could not be updated.';
        }

        return back()->with($failedCount > 0 ? 'warning' : 'success', $message);
    }

    public function resendNotification(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('manage', $order);

        $data = $request->validate([
            'notification' => ['required', 'in:placed,shipped'],
        ]);

        $this->orderManagementService->resendNotification($order, (string) $data['notification']);

        return back()->with('success', 'Customer notification has been queued.');
    }
}
