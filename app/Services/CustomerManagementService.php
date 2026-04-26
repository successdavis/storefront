<?php

namespace App\Services;

use App\Mail\AdminCustomerMessageMail;
use App\Models\Cart;
use App\Models\CustomerActivityLog;
use App\Models\CustomerInvoice;
use App\Models\CustomerNote;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerManagementService
{
    public const HIGH_VALUE_THRESHOLD = 100000.00;

    public const BULK_ACTION_ACTIVATE = 'activate';
    public const BULK_ACTION_DEACTIVATE = 'deactivate';
    public const BULK_ACTION_SUSPEND = 'suspend';
    public const BULK_ACTION_MARK_VIP = 'mark_vip';
    public const BULK_ACTION_CLEAR_VIP = 'clear_vip';
    public const BULK_ACTION_FLAG_RISK = 'flag_risk';
    public const BULK_ACTION_CLEAR_RISK = 'clear_risk';

    protected ?bool $notesTableExists = null;
    protected ?bool $activityTableExists = null;

    public function __construct(
        protected OrderManagementService $orderManagementService,
        protected CustomerSavedItemService $customerSavedItemService,
        protected CartService $cartService,
        protected ProductService $productService,
    ) {}

    public function listCustomers(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 15)));

        return $this->customersQuery($filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function summaryCards(array $filters = []): array
    {
        $base = $this->customersQuery($filters, applySorting: false);

        return [
            ['key' => 'total', 'label' => 'Customers', 'value' => (clone $base)->count()],
            ['key' => 'verified', 'label' => 'Verified', 'value' => (clone $base)->whereNotNull('users.email_verified_at')->count()],
            ['key' => 'with_orders', 'label' => 'With orders', 'value' => (clone $base)->whereHas('orders')->count()],
            ['key' => 'vip', 'label' => 'VIP', 'value' => $this->supportsUserFlags() ? (clone $base)->where('users.is_vip', true)->count() : 0],
            ['key' => 'suspended', 'label' => 'Suspended', 'value' => $this->supportsUserStatus() ? (clone $base)->where('users.status', User::STATUS_SUSPENDED)->count() : 0],
            ['key' => 'high_value', 'label' => 'High value', 'value' => (clone $base)->havingRaw('COALESCE(total_spend, 0) >= ?', [self::HIGH_VALUE_THRESHOLD])->count()],
        ];
    }

    public function filterOptions(): array
    {
        return [
            'statuses' => [
                ['value' => '', 'label' => 'All statuses'],
                ['value' => User::STATUS_ACTIVE, 'label' => 'Active'],
                ['value' => User::STATUS_INACTIVE, 'label' => 'Inactive'],
                ['value' => User::STATUS_SUSPENDED, 'label' => 'Suspended'],
            ],
            'verification' => [
                ['value' => '', 'label' => 'All email states'],
                ['value' => 'verified', 'label' => 'Verified'],
                ['value' => 'unverified', 'label' => 'Unverified'],
            ],
            'order_presence' => [
                ['value' => '', 'label' => 'All customers'],
                ['value' => 'with_orders', 'label' => 'Has orders'],
                ['value' => 'without_orders', 'label' => 'No orders'],
            ],
            'dormant_days' => [
                ['value' => '', 'label' => 'Any recency'],
                ['value' => '30', 'label' => 'No order in 30+ days'],
                ['value' => '60', 'label' => 'No order in 60+ days'],
                ['value' => '90', 'label' => 'No order in 90+ days'],
                ['value' => '180', 'label' => 'No order in 180+ days'],
            ],
            'sorts' => [
                ['value' => 'newest', 'label' => 'Newest'],
                ['value' => 'oldest', 'label' => 'Oldest'],
                ['value' => 'name_asc', 'label' => 'Name A-Z'],
                ['value' => 'total_orders_desc', 'label' => 'Total orders'],
                ['value' => 'total_spend_desc', 'label' => 'Total spend'],
                ['value' => 'last_login_desc', 'label' => 'Last login'],
                ['value' => 'last_order_desc', 'label' => 'Last order date'],
            ],
            'high_value_threshold' => self::HIGH_VALUE_THRESHOLD,
        ];
    }

    public function toListPayload(User $customer): array
    {
        $totalOrders = (int) ($customer->total_orders ?? 0);
        $totalSpend = (float) ($customer->total_spend ?? 0);
        $lastOrderAt = $customer->getAttribute('last_order_at');

        return [
            'id' => (int) $customer->id,
            'customer_slug' => $customer->customer_slug,
            'route_key' => $customer->customerRouteKey(),
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'status' => $customer->status ?? User::STATUS_ACTIVE,
            'status_label' => $this->statusLabel($customer->status ?? User::STATUS_ACTIVE),
            'email_verified' => $customer->hasVerifiedEmail(),
            'email_verified_at' => optional($customer->email_verified_at)?->toIso8601String(),
            'total_orders' => $totalOrders,
            'total_spend' => $totalSpend,
            'date_registered' => optional($customer->created_at)?->toIso8601String(),
            'last_login_at' => optional($customer->last_login_at)?->toIso8601String(),
            'last_order_at' => $lastOrderAt ? Carbon::parse($lastOrderAt)->toIso8601String() : null,
            'is_vip' => (bool) ($customer->is_vip ?? false),
            'is_risky' => (bool) ($customer->is_risky ?? false),
            'segment' => $this->segmentForCustomer(
                totalOrders: $totalOrders,
                totalSpend: $totalSpend,
                firstOrderAt: $customer->getAttribute('first_order_at'),
                lastOrderAt: $lastOrderAt,
            ),
        ];
    }

    public function detailPayload(User $customer): array
    {
        $customer->loadMissing([
            'customerAddresses.country:id,name',
            'customerAddresses.state:id,name',
            'customerAddresses.lga:id,name',
        ]);

        $orders = $this->ordersPaginator($customer);
        $summary = $this->orderSummary($customer);
        $receivables = $this->receivableSummary($customer);
        $wishlistCounts = $this->customerSavedItemService->counts($customer);
        $cartInsight = $this->cartInsight($customer);
        $lastSeenAt = $this->lastSeenAt($customer);

        return [
            'id' => (int) $customer->id,
            'customer_slug' => $customer->customer_slug,
            'route_key' => $customer->customerRouteKey(),
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'status' => $customer->status ?? User::STATUS_ACTIVE,
            'status_label' => $this->statusLabel($customer->status ?? User::STATUS_ACTIVE),
            'email_verified' => $customer->hasVerifiedEmail(),
            'email_verified_at' => optional($customer->email_verified_at)?->toIso8601String(),
            'registered_at' => optional($customer->created_at)?->toIso8601String(),
            'last_login_at' => optional($customer->last_login_at)?->toIso8601String(),
            'last_login_ip' => $customer->last_login_ip,
            'login_count' => (int) ($customer->login_count ?? 0),
            'last_seen_at' => optional($lastSeenAt)?->toIso8601String(),
            'is_vip' => (bool) ($customer->is_vip ?? false),
            'is_risky' => (bool) ($customer->is_risky ?? false),
            'segment' => $this->segmentForCustomer(
                totalOrders: (int) $summary['total_orders'],
                totalSpend: (float) $summary['total_spend'],
                firstOrderAt: $summary['first_order_at'],
                lastOrderAt: $summary['last_order_at'],
            ),
            'overview_cards' => [
                ['key' => 'orders', 'label' => 'Total orders', 'value' => (int) $summary['total_orders']],
                ['key' => 'spend', 'label' => 'Lifetime spend', 'value' => (float) $summary['total_spend']],
                ['key' => 'aov', 'label' => 'Average order value', 'value' => (float) $summary['average_order_value']],
                ['key' => 'addresses', 'label' => 'Saved addresses', 'value' => (int) $customer->customerAddresses->count()],
            ],
            'commerce_summary' => $summary,
            'receivables' => $receivables,
            'analytics' => [
                'preferred_payment_method' => $this->preferredPaymentMethod($customer),
                'top_category' => $this->topCategory($customer),
                'wishlist_count' => $wishlistCounts['wishlist'] ?? 0,
                'saved_for_later_count' => $wishlistCounts['saved_for_later'] ?? 0,
                'abandoned_cart_candidate' => (bool) ($cartInsight['is_abandoned_candidate'] ?? false),
            ],
            'addresses' => $this->addressesPayload($customer),
            'orders' => $orders->through(fn (Order $order) => $this->orderManagementService->toAdminListPayload($order)),
            'payments' => $this->recentPayments($customer),
            'cart' => $cartInsight,
            'notes_enabled' => $this->notesTableExists(),
            'activity_enabled' => $this->activityTableExists(),
            'notes' => $this->notesPayload($customer),
            'activity_log' => $this->activityPayload($customer),
            'communication_log' => $this->communicationPayload($customer),
        ];
    }

    public function updateCustomer(User $customer, array $data, User $actor): User
    {
        $changes = [];

        foreach (['name', 'email', 'phone', 'address'] as $field) {
            if (array_key_exists($field, $data) && $customer->{$field} !== $data[$field]) {
                $changes[$field] = [
                    'from' => $customer->{$field},
                    'to' => $data[$field],
                ];
            }
        }

        foreach (['is_vip', 'is_risky'] as $field) {
            if (array_key_exists($field, $data) && (bool) $customer->{$field} !== (bool) $data[$field]) {
                $changes[$field] = [
                    'from' => (bool) $customer->{$field},
                    'to' => (bool) $data[$field],
                ];
            }
        }

        if (!empty($changes)) {
            $customer->fill($data);

            if (array_key_exists('email', $data) && $customer->isDirty('email')) {
                $customer->email_verified_at = null;
            }

            $customer->save();

            $this->logActivity(
                customer: $customer,
                type: CustomerActivityLog::TYPE_ACCOUNT,
                action: 'profile_updated',
                message: 'Customer profile updated by admin.',
                actor: $actor,
                meta: ['changes' => $changes],
            );
        }

        return $customer->fresh();
    }

    public function changeStatus(User $customer, string $status, User $actor, ?string $note = null): User
    {
        if (!in_array($status, [User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Unsupported customer status supplied.',
            ]);
        }

        $previous = $customer->status ?? User::STATUS_ACTIVE;

        if ($previous === $status) {
            return $customer;
        }

        $customer->forceFill(['status' => $status])->save();

        $this->logActivity(
            customer: $customer,
            type: CustomerActivityLog::TYPE_ACCOUNT,
            action: 'status_changed',
            message: "Customer account status changed to {$this->statusLabel($status)}.",
            actor: $actor,
            meta: [
                'previous_status' => $previous,
                'new_status' => $status,
                'note' => $note,
            ],
        );

        return $customer->fresh();
    }

    public function markEmailVerified(User $customer, User $actor): User
    {
        if ($customer->hasVerifiedEmail()) {
            return $customer;
        }

        $customer->forceFill(['email_verified_at' => now()])->save();

        $this->logActivity(
            customer: $customer,
            type: CustomerActivityLog::TYPE_SECURITY,
            action: 'email_marked_verified',
            message: 'Customer email was marked verified by admin.',
            actor: $actor,
        );

        return $customer->fresh();
    }

    public function resendVerificationEmail(User $customer, User $actor): void
    {
        $this->assertCustomerHasMailableEmail($customer, 'email');

        if ($customer->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => 'This customer already has a verified email address.',
            ]);
        }

        $customer->sendEmailVerificationNotification();

        $this->logActivity(
            customer: $customer,
            type: CustomerActivityLog::TYPE_SECURITY,
            action: 'verification_resent',
            message: 'Verification email resent by admin.',
            actor: $actor,
        );
    }

    public function sendPasswordReset(User $customer, User $actor): void
    {
        $this->assertCustomerHasMailableEmail($customer, 'email');

        $response = Password::broker()->sendResetLink([
            'email' => $customer->email,
        ]);

        if ($response !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => __($response),
            ]);
        }

        $this->logActivity(
            customer: $customer,
            type: CustomerActivityLog::TYPE_SECURITY,
            action: 'password_reset_sent',
            message: 'Password reset email sent by admin.',
            actor: $actor,
        );
    }

    public function sendEmail(User $customer, User $actor, string $subject, string $message): void
    {
        $this->assertCustomerHasMailableEmail($customer, 'email');

        Mail::to($customer->email)->queue(new AdminCustomerMessageMail(
            customer: $customer,
            subjectLine: trim($subject),
            messageBody: trim($message),
            senderName: $actor->name,
        ));

        $this->logActivity(
            customer: $customer,
            type: CustomerActivityLog::TYPE_COMMUNICATION,
            action: 'email_sent',
            message: 'Custom email queued from admin.',
            actor: $actor,
            meta: [
                'subject' => trim($subject),
                'body_excerpt' => Str::limit(trim($message), 240),
            ],
        );
    }

    public function storeNote(User $customer, User $actor, string $note): ?CustomerNote
    {
        if (!$this->notesTableExists()) {
            return null;
        }

        $created = $customer->customerNotes()->create([
            'author_id' => $actor->id,
            'note' => trim($note),
        ]);

        $this->logActivity(
            customer: $customer,
            type: CustomerActivityLog::TYPE_NOTE,
            action: 'note_added',
            message: 'Internal customer note added.',
            actor: $actor,
            meta: [
                'note_excerpt' => Str::limit(trim($note), 180),
                'note_id' => $created->id,
            ],
        );

        return $created->fresh('author:id,name,email');
    }

    public function updateNote(CustomerNote $note, string $value, User $actor): ?CustomerNote
    {
        if (!$this->notesTableExists()) {
            return null;
        }

        $note->update([
            'note' => trim($value),
        ]);

        $this->logActivity(
            customer: $note->customer,
            type: CustomerActivityLog::TYPE_NOTE,
            action: 'note_updated',
            message: 'Internal customer note updated.',
            actor: $actor,
            meta: [
                'note_excerpt' => Str::limit(trim($value), 180),
                'note_id' => $note->id,
            ],
        );

        return $note->fresh('author:id,name,email');
    }

    public function deleteNote(CustomerNote $note, User $actor): bool
    {
        if (!$this->notesTableExists()) {
            return false;
        }

        $customer = $note->customer;
        $noteId = $note->id;
        $excerpt = Str::limit((string) $note->note, 180);
        $note->delete();

        if ($customer) {
            $this->logActivity(
                customer: $customer,
                type: CustomerActivityLog::TYPE_NOTE,
                action: 'note_deleted',
                message: 'Internal customer note deleted.',
                actor: $actor,
                meta: [
                    'note_excerpt' => $excerpt,
                    'note_id' => $noteId,
                ],
            );
        }

        return true;
    }

    public function bulkAction(array $customerIds, string $action, User $actor): array
    {
        $successCount = 0;
        $failed = [];

        $customers = User::query()
            ->role(RoleNames::CUSTOMER)
            ->whereIn('id', $customerIds)
            ->get();

        foreach ($customers as $customer) {
            try {
                match ($action) {
                    self::BULK_ACTION_ACTIVATE => $this->changeStatus($customer, User::STATUS_ACTIVE, $actor, 'Bulk activation'),
                    self::BULK_ACTION_DEACTIVATE => $this->changeStatus($customer, User::STATUS_INACTIVE, $actor, 'Bulk deactivation'),
                    self::BULK_ACTION_SUSPEND => $this->changeStatus($customer, User::STATUS_SUSPENDED, $actor, 'Bulk suspension'),
                    self::BULK_ACTION_MARK_VIP => $this->updateCustomer($customer, ['is_vip' => true], $actor),
                    self::BULK_ACTION_CLEAR_VIP => $this->updateCustomer($customer, ['is_vip' => false], $actor),
                    self::BULK_ACTION_FLAG_RISK => $this->updateCustomer($customer, ['is_risky' => true], $actor),
                    self::BULK_ACTION_CLEAR_RISK => $this->updateCustomer($customer, ['is_risky' => false], $actor),
                    default => throw ValidationException::withMessages([
                        'action' => 'Unsupported bulk customer action.',
                    ]),
                };

                $successCount++;
            } catch (\Throwable $exception) {
                $failed[] = [
                    'customer_id' => (int) $customer->id,
                    'name' => $customer->name,
                    'message' => $exception instanceof ValidationException
                        ? collect($exception->errors())->flatten()->first()
                        : $exception->getMessage(),
                ];
            }
        }

        return [
            'success_count' => $successCount,
            'failed' => $failed,
        ];
    }

    public function exportRows(array $filters = [], ?array $selectedIds = null): Collection
    {
        $query = $this->customersQuery($filters, applySorting: true);

        if (!empty($selectedIds)) {
            $query->whereIn('users.id', $selectedIds);
        }

        return $query->get()->map(fn (User $customer) => [
            'id' => (int) $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'status' => $customer->status ?? User::STATUS_ACTIVE,
            'email_verified' => $customer->hasVerifiedEmail() ? 'Yes' : 'No',
            'registered_at' => optional($customer->created_at)?->toDateTimeString(),
            'last_login_at' => optional($customer->last_login_at)?->toDateTimeString(),
            'total_orders' => (int) ($customer->total_orders ?? 0),
            'total_spend' => round((float) ($customer->total_spend ?? 0), 2),
            'last_order_at' => $customer->getAttribute('last_order_at'),
            'vip' => (bool) ($customer->is_vip ?? false) ? 'Yes' : 'No',
            'risky' => (bool) ($customer->is_risky ?? false) ? 'Yes' : 'No',
        ])->values();
    }

    public function bulkActions(): array
    {
        return [
            ['value' => self::BULK_ACTION_MARK_VIP, 'label' => 'Mark VIP'],
            ['value' => self::BULK_ACTION_CLEAR_VIP, 'label' => 'Clear VIP'],
            ['value' => self::BULK_ACTION_FLAG_RISK, 'label' => 'Flag risk'],
            ['value' => self::BULK_ACTION_CLEAR_RISK, 'label' => 'Clear risk'],
        ];
    }

    protected function customersQuery(array $filters = [], bool $applySorting = true): Builder
    {
        $query = User::query()
            ->role(RoleNames::CUSTOMER)
            ->select('users.*')
            ->withCount(['orders as total_orders'])
            ->withSum([
                'orders as total_spend' => fn (Builder $builder) => $builder->whereIn('status', ['paid', 'shipped', 'completed']),
            ], 'total_amount')
            ->selectSub(
                Order::query()
                    ->selectRaw('MAX(created_at)')
                    ->whereColumn('orders.user_id', 'users.id'),
                'last_order_at'
            )
            ->selectSub(
                Order::query()
                    ->selectRaw('MIN(created_at)')
                    ->whereColumn('orders.user_id', 'users.id'),
                'first_order_at'
            );

        $this->applyFilters($query, $filters);

        if ($applySorting) {
            $this->applySorting($query, $filters);
        }

        return $query;
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $emailState = trim((string) ($filters['email_verified'] ?? ''));
        $orderPresence = trim((string) ($filters['has_orders'] ?? ''));
        $registeredFrom = trim((string) ($filters['registered_from'] ?? ''));
        $registeredTo = trim((string) ($filters['registered_to'] ?? ''));
        $lastLoginFrom = trim((string) ($filters['last_login_from'] ?? ''));
        $lastLoginTo = trim((string) ($filters['last_login_to'] ?? ''));
        $highValue = filter_var($filters['high_value'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $dormantDays = (int) ($filters['dormant_days'] ?? 0);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.phone', 'like', "%{$search}%")
                    ->orWhereHas('orders', function (Builder $orderQuery) use ($search) {
                        $orderQuery->where('order_number', 'like', "%{$search}%");
                    });

                if (is_numeric($search)) {
                    $builder->orWhere('users.id', (int) $search);
                }
            });
        }

        if ($this->supportsUserStatus() && in_array($status, [User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED], true)) {
            $query->where('users.status', $status);
        }

        if ($emailState === 'verified') {
            $query->whereNotNull('users.email_verified_at');
        } elseif ($emailState === 'unverified') {
            $query->whereNull('users.email_verified_at');
        }

        if ($orderPresence === 'with_orders') {
            $query->whereHas('orders');
        } elseif ($orderPresence === 'without_orders') {
            $query->whereDoesntHave('orders');
        }

        if ($registeredFrom !== '') {
            $query->whereDate('users.created_at', '>=', $registeredFrom);
        }

        if ($registeredTo !== '') {
            $query->whereDate('users.created_at', '<=', $registeredTo);
        }

        if ($this->supportsLastLogin() && $lastLoginFrom !== '') {
            $query->whereDate('users.last_login_at', '>=', $lastLoginFrom);
        }

        if ($this->supportsLastLogin() && $lastLoginTo !== '') {
            $query->whereDate('users.last_login_at', '<=', $lastLoginTo);
        }

        if ($highValue) {
            $query->havingRaw('COALESCE(total_spend, 0) >= ?', [self::HIGH_VALUE_THRESHOLD]);
        }

        if ($dormantDays > 0) {
            $cutoff = now()->subDays($dormantDays)->toDateTimeString();
            $query->havingRaw('(last_order_at IS NULL OR last_order_at < ?)', [$cutoff]);
        }
    }

    protected function applySorting(Builder $query, array $filters): void
    {
        $sort = (string) ($filters['sort'] ?? 'newest');

        match ($sort) {
            'oldest' => $query->oldest('users.created_at'),
            'name_asc' => $query->orderBy('users.name'),
            'total_orders_desc' => $query->orderByDesc('total_orders')->orderBy('users.name'),
            'total_spend_desc' => $query->orderByDesc('total_spend')->orderBy('users.name'),
            'last_login_desc' => $this->supportsLastLogin()
                ? $query->orderByDesc('users.last_login_at')->orderByDesc('users.created_at')
                : $query->latest('users.created_at'),
            'last_order_desc' => $query->orderByDesc('last_order_at')->orderByDesc('users.created_at'),
            default => $query->latest('users.created_at'),
        };
    }

    protected function orderSummary(User $customer): array
    {
        $summary = Order::query()
            ->where('user_id', $customer->id)
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("SUM(CASE WHEN status IN ('paid', 'shipped', 'completed') THEN 1 ELSE 0 END) as paid_orders")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('paid', 'shipped', 'completed') THEN total_amount ELSE 0 END), 0) as total_spend")
            ->selectRaw("COALESCE(AVG(CASE WHEN status IN ('paid', 'shipped', 'completed') THEN total_amount END), 0) as average_order_value")
            ->selectRaw('MIN(created_at) as first_order_at')
            ->selectRaw('MAX(created_at) as last_order_at')
            ->first();

        $refundedOrders = Payment::query()
            ->join('orders', function ($join) {
                $join->on('orders.id', '=', 'payments.payable_id')
                    ->where('payments.payable_type', Order::class);
            })
            ->where('orders.user_id', $customer->id)
            ->where('payments.status', 'refunded')
            ->distinct('orders.id')
            ->count('orders.id');

        return [
            'total_orders' => (int) ($summary->total_orders ?? 0),
            'paid_orders' => (int) ($summary->paid_orders ?? 0),
            'cancelled_orders' => (int) ($summary->cancelled_orders ?? 0),
            'refunded_orders' => (int) $refundedOrders,
            'total_spend' => round((float) ($summary->total_spend ?? 0), 2),
            'average_order_value' => round((float) ($summary->average_order_value ?? 0), 2),
            'first_order_at' => $summary->first_order_at ? Carbon::parse($summary->first_order_at)->toIso8601String() : null,
            'last_order_at' => $summary->last_order_at ? Carbon::parse($summary->last_order_at)->toIso8601String() : null,
        ];
    }

    protected function ordersPaginator(User $customer): LengthAwarePaginator
    {
        return Order::query()
            ->where('user_id', $customer->id)
            ->with([
                'user:id,name,email,phone',
                'shipment:id,shippable_id,shippable_type,status,type,courier_name,tracking_number,ready_at,shipped_at,delivered_at',
                'payments:id,payable_id,payable_type,amount,status,method,paid_at,transaction_reference',
            ])
            ->withCount('items')
            ->withSum('items as item_quantity', 'quantity')
            ->latest()
            ->paginate(10, ['*'], 'orders_page')
            ->withQueryString();
    }

    protected function addressesPayload(User $customer): array
    {
        return $customer->customerAddresses
            ->sortByDesc('is_default')
            ->values()
            ->map(function ($address) {
                return [
                    'id' => (int) $address->id,
                    'label' => $address->label,
                    'recipient_name' => $address->recipient_name,
                    'phone' => $address->phone,
                    'email' => $address->email,
                    'line1' => $address->line1,
                    'line2' => $address->line2,
                    'postal_code' => $address->postal_code,
                    'country' => $address->country?->name,
                    'state' => $address->state?->name,
                    'lga' => $address->lga?->name,
                    'is_default' => (bool) $address->is_default,
                    'created_at' => optional($address->created_at)?->toIso8601String(),
                ];
            })
            ->all();
    }

    protected function recentPayments(User $customer): array
    {
        $orderPayments = Payment::query()
            ->select('payments.*', 'orders.order_number')
            ->join('orders', function ($join) {
                $join->on('orders.id', '=', 'payments.payable_id')
                    ->where('payments.payable_type', Order::class);
            })
            ->where('orders.user_id', $customer->id)
            ->latest('payments.created_at')
            ->limit(12)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => (int) $payment->id,
                'order_number' => $payment->order_number,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'method' => $payment->method,
                'transaction_reference' => $payment->transaction_reference,
                'paid_at' => optional($payment->paid_at)?->toIso8601String(),
                'created_at' => optional($payment->created_at)?->toIso8601String(),
                'source' => 'order',
            ])
            ->values();

        $invoicePayments = Payment::query()
            ->select('payments.*', 'customer_invoices.invoice_number')
            ->join('customer_invoices', function ($join) {
                $join->on('customer_invoices.id', '=', 'payments.payable_id')
                    ->where('payments.payable_type', CustomerInvoice::class);
            })
            ->where('customer_invoices.customer_id', $customer->id)
            ->latest('payments.created_at')
            ->limit(12)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => (int) $payment->id,
                'order_number' => $payment->invoice_number,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'method' => $payment->method,
                'transaction_reference' => $payment->transaction_reference,
                'paid_at' => optional($payment->paid_at)?->toIso8601String(),
                'created_at' => optional($payment->created_at)?->toIso8601String(),
                'source' => 'invoice',
            ])
            ->values();

        return $orderPayments
            ->concat($invoicePayments)
            ->sortByDesc('created_at')
            ->take(12)
            ->values()
            ->all();
    }

    protected function receivableSummary(User $customer): array
    {
        $base = CustomerInvoice::query()
            ->where('customer_id', $customer->id);

        $outstandingBalance = round((float) (clone $base)->sum('outstanding_balance'), 2);
        $overdueBalance = round((float) (clone $base)
            ->where('outstanding_balance', '>', 0)
            ->whereDate('due_date', '<', now()->toDateString())
            ->sum('outstanding_balance'), 2);

        $invoiceRows = (clone $base)
            ->with('order:id,order_number,total_amount')
            ->latest('issued_at')
            ->limit(12)
            ->get()
            ->map(fn (CustomerInvoice $invoice) => [
                'id' => (int) $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'status_label' => $this->statusLabel($invoice->status),
                'total_amount' => (float) $invoice->total_amount,
                'amount_paid' => (float) $invoice->amount_paid,
                'outstanding_balance' => (float) $invoice->outstanding_balance,
                'due_date' => optional($invoice->due_date)?->toDateString(),
                'issued_at' => optional($invoice->issued_at)?->toIso8601String(),
                'order' => $invoice->order ? [
                    'id' => (int) $invoice->order->id,
                    'order_number' => $invoice->order->order_number,
                    'total_amount' => (float) $invoice->order->total_amount,
                ] : null,
            ])
            ->values()
            ->all();

        $creditLimit = $outstandingBalance + round((float) (clone $base)->sum('amount_paid'), 2);
        $creditUtilization = $creditLimit > 0
            ? round(($outstandingBalance / $creditLimit) * 100, 2)
            : 0.0;

        return [
            'outstanding_balance' => $outstandingBalance,
            'overdue_balance' => $overdueBalance,
            'open_invoices_count' => (int) (clone $base)->where('outstanding_balance', '>', 0)->count(),
            'credit_utilization_percent' => $creditUtilization,
            'invoices' => $invoiceRows,
        ];
    }

    protected function cartInsight(User $customer): array
    {
        $cartData = $this->cartService->getDetailedCart(null, $customer->id);
        $cart = $cartData['cart'] ?? [];
        $summary = $cartData['summary'] ?? [];
        $items = collect($cart['items'] ?? []);
        $lastUpdatedAt = $items
            ->pluck('updated_at')
            ->filter()
            ->sortDesc()
            ->first();

        $latestConvertedCart = Cart::query()
            ->where('user_id', $customer->id)
            ->withCount('items')
            ->latest('created_at')
            ->first();

        return [
            'has_active_cart' => $items->isNotEmpty(),
            'item_count' => (int) ($summary['item_count'] ?? 0),
            'cart_value_estimate' => (float) ($summary['total'] ?? 0),
            'last_cart_update_at' => $lastUpdatedAt,
            'is_abandoned_candidate' => $lastUpdatedAt
                ? Carbon::parse($lastUpdatedAt)->lte(now()->subHours(24))
                : false,
            'items' => $items->take(5)->map(fn (array $item) => [
                'variant_id' => $item['variant_id'],
                'product_name' => data_get($item, 'product.name'),
                'variant_label' => data_get($item, 'variant.label'),
                'quantity' => (int) ($item['quantity'] ?? 0),
                'subtotal' => (float) ($item['subtotal'] ?? 0),
                'availability' => data_get($item, 'availability.message'),
            ])->values()->all(),
            'latest_converted_cart' => $latestConvertedCart ? [
                'id' => (int) $latestConvertedCart->id,
                'status' => $latestConvertedCart->status,
                'items_count' => (int) ($latestConvertedCart->items_count ?? 0),
                'created_at' => optional($latestConvertedCart->created_at)?->toIso8601String(),
            ] : null,
        ];
    }

    protected function notesPayload(User $customer): array
    {
        if (!$this->notesTableExists()) {
            return [];
        }

        return $customer->customerNotes()
            ->with('author:id,name,email')
            ->get()
            ->map(fn (CustomerNote $note) => [
                'id' => (int) $note->id,
                'note' => $note->note,
                'author' => $note->author ? [
                    'id' => (int) $note->author->id,
                    'name' => $note->author->name,
                    'email' => $note->author->email,
                ] : null,
                'created_at' => optional($note->created_at)?->toIso8601String(),
                'updated_at' => optional($note->updated_at)?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    protected function activityPayload(User $customer): array
    {
        if (!$this->activityTableExists()) {
            return [];
        }

        return $customer->customerActivityLogs()
            ->where('type', '!=', CustomerActivityLog::TYPE_COMMUNICATION)
            ->with('actor:id,name,email')
            ->limit(20)
            ->get()
            ->map(fn (CustomerActivityLog $entry) => $this->mapActivityEntry($entry))
            ->values()
            ->all();
    }

    protected function communicationPayload(User $customer): array
    {
        if (!$this->activityTableExists()) {
            return [];
        }

        return $customer->customerActivityLogs()
            ->where('type', CustomerActivityLog::TYPE_COMMUNICATION)
            ->with('actor:id,name,email')
            ->limit(20)
            ->get()
            ->map(fn (CustomerActivityLog $entry) => $this->mapActivityEntry($entry))
            ->values()
            ->all();
    }

    protected function mapActivityEntry(CustomerActivityLog $entry): array
    {
        return [
            'id' => (int) $entry->id,
            'type' => $entry->type,
            'action' => $entry->action,
            'message' => $entry->message,
            'meta' => $entry->meta,
            'actor' => $entry->actor ? [
                'id' => (int) $entry->actor->id,
                'name' => $entry->actor->name,
                'email' => $entry->actor->email,
            ] : null,
            'created_at' => optional($entry->created_at)?->toIso8601String(),
        ];
    }

    protected function topCategory(User $customer): ?array
    {
        $row = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('category_product', 'category_product.product_id', '=', 'products.id')
            ->join('categories', 'categories.id', '=', 'category_product.category_id')
            ->where('orders.user_id', $customer->id)
            ->selectRaw('categories.id, categories.name, SUM(order_items.quantity) as quantity')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('quantity')
            ->first();

        return $row ? [
            'id' => (int) $row->id,
            'name' => $row->name,
            'quantity' => (int) $row->quantity,
        ] : null;
    }

    protected function preferredPaymentMethod(User $customer): ?string
    {
        $row = Payment::query()
            ->selectRaw('payments.method, COUNT(*) as total')
            ->join('orders', function ($join) {
                $join->on('orders.id', '=', 'payments.payable_id')
                    ->where('payments.payable_type', Order::class);
            })
            ->where('orders.user_id', $customer->id)
            ->groupBy('payments.method')
            ->orderByDesc('total')
            ->first();

        return $row?->method;
    }

    protected function lastSeenAt(User $customer): ?Carbon
    {
        $timestamp = DB::table('sessions')
            ->where('user_id', $customer->id)
            ->max('last_activity');

        return $timestamp ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }

    protected function segmentForCustomer(int $totalOrders, float $totalSpend, ?string $firstOrderAt, ?string $lastOrderAt): string
    {
        if ($totalOrders === 0) {
            return 'new';
        }

        if ($lastOrderAt && Carbon::parse($lastOrderAt)->lt(now()->subDays(90))) {
            return 'dormant';
        }

        if ($totalSpend >= self::HIGH_VALUE_THRESHOLD) {
            return 'high_value';
        }

        if ($totalOrders >= 5) {
            return 'loyal';
        }

        if ($totalOrders >= 2) {
            return 'repeat';
        }

        if ($firstOrderAt && Carbon::parse($firstOrderAt)->gte(now()->subDays(30))) {
            return 'new';
        }

        return 'at_risk';
    }

    protected function statusLabel(string $status): string
    {
        return Str::of($status)->replace('_', ' ')->headline()->value();
    }

    protected function logActivity(User $customer, string $type, string $action, string $message, ?User $actor = null, array $meta = []): void
    {
        if (!$this->activityTableExists()) {
            return;
        }

        $customer->customerActivityLogs()->create([
            'actor_id' => $actor?->id,
            'type' => $type,
            'action' => $action,
            'message' => $message,
            'meta' => $meta ?: null,
        ]);
    }

    protected function assertCustomerHasMailableEmail(User $customer, string $key = 'email'): void
    {
        if (!$customer->hasRealEmail()) {
            throw ValidationException::withMessages([
                $key => 'This customer does not have a deliverable email address.',
            ]);
        }
    }

    protected function notesTableExists(): bool
    {
        return $this->notesTableExists ??= Schema::hasTable('customer_notes');
    }

    protected function activityTableExists(): bool
    {
        return $this->activityTableExists ??= Schema::hasTable('customer_activity_logs');
    }

    protected function supportsUserStatus(): bool
    {
        return Schema::hasColumn('users', 'status');
    }

    protected function supportsLastLogin(): bool
    {
        return Schema::hasColumn('users', 'last_login_at');
    }

    protected function supportsUserFlags(): bool
    {
        return Schema::hasColumn('users', 'is_vip') && Schema::hasColumn('users', 'is_risky');
    }
}
