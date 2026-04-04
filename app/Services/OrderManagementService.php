<?php

namespace App\Services;

use App\Events\OrderLifecycleChanged;
use App\Models\Order;
use App\Models\OrderNote;
use App\Models\OrderStatusHistory;
use App\Models\Shipment;
use App\Models\StockEntry;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderShippedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderManagementService
{
    public const HISTORY_TYPE_ORDER = 'order_status';
    public const HISTORY_TYPE_PAYMENT = 'payment_status';
    public const HISTORY_TYPE_FULFILLMENT = 'fulfillment_status';

    public const ACTION_MARK_PAYMENT_PAID = 'mark_payment_paid';
    public const ACTION_MARK_PROCESSING = 'mark_processing';
    public const ACTION_MARK_PACKED = 'mark_packed';
    public const ACTION_MARK_SHIPPED = 'mark_shipped';
    public const ACTION_MARK_READY_FOR_PICKUP = 'mark_ready_for_pickup';
    public const ACTION_MARK_DELIVERED = 'mark_delivered';
    public const ACTION_CANCEL = 'cancel';

    public const PAYMENT_METHODS = [
        'cash',
        'card',
        'transfer',
        'wallet',
        'paypal',
        'stripe',
        'cheque',
    ];

    public function __construct(
        protected InventoryService $inventoryService,
        protected ProductService $productService,
    ) {}

    public function listOrders(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 15)));

        $query = Order::query()
            ->with([
                'user:id,name,email,phone',
                'shipment:id,shippable_id,shippable_type,status,type,courier_name,tracking_number,tracking_url,ready_at,shipped_at,delivered_at',
                'payments:id,payable_id,payable_type,amount,status,method,paid_at,transaction_reference',
            ])
            ->withCount('items')
            ->withSum('items as item_quantity', 'quantity');

        $this->applyAdminFilters($query, $filters);
        $this->applyAdminSorting($query, $filters);

        return $query->paginate($perPage)->withQueryString();
    }

    public function listCustomerOrders(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->with([
                'shipment:id,shippable_id,shippable_type,status,type',
                'payments:id,payable_id,payable_type,amount,status,method,paid_at',
            ])
            ->withCount('items')
            ->withSum('items as item_quantity', 'quantity')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function summaryCards(array $filters = []): array
    {
        $base = Order::query();
        $this->applyAdminFilters($base, $filters);

        return [
            ['key' => 'total', 'label' => 'Total Orders', 'value' => (clone $base)->count()],
            ['key' => 'pending', 'label' => 'Pending', 'value' => (clone $base)->where('orders.status', 'pending')->count()],
            ['key' => 'paid', 'label' => 'Paid', 'value' => (clone $base)->where('orders.status', 'paid')->count()],
            ['key' => 'processing', 'label' => 'Processing', 'value' => (clone $base)->whereHas('shipment', fn (Builder $query) => $query->whereIn('status', ['processing', 'packed']))->count()],
            ['key' => 'shipped', 'label' => 'Shipped', 'value' => (clone $base)->where(function (Builder $query) {
                $query->where('orders.status', 'shipped')
                    ->orWhereHas('shipment', fn (Builder $shipment) => $shipment->whereIn('status', ['shipped', 'ready']));
            })->count()],
            ['key' => 'delivered', 'label' => 'Delivered / Completed', 'value' => (clone $base)->where(function (Builder $query) {
                $query->where('orders.status', 'completed')
                    ->orWhereHas('shipment', fn (Builder $shipment) => $shipment->whereIn('status', ['delivered', 'completed']));
            })->count()],
            ['key' => 'cancelled', 'label' => 'Cancelled', 'value' => (clone $base)->where('orders.status', 'cancelled')->count()],
            ['key' => 'refunded', 'label' => 'Refunded', 'value' => (clone $base)->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('payments')
                    ->whereColumn('payments.payable_id', 'orders.id')
                    ->where('payments.payable_type', Order::class)
                    ->where('payments.status', 'refunded');
            })->count()],
        ];
    }

    public function filterOptions(): array
    {
        return [
            'payment_statuses' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'partially_paid', 'label' => 'Partially paid'],
                ['value' => 'paid', 'label' => 'Paid'],
                ['value' => 'failed', 'label' => 'Failed'],
                ['value' => 'refunded', 'label' => 'Refunded'],
            ],
            'order_statuses' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'paid', 'label' => 'Paid'],
                ['value' => 'shipped', 'label' => 'Shipped'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
            'fulfillment_statuses' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'processing', 'label' => 'Processing'],
                ['value' => 'packed', 'label' => 'Packed'],
                ['value' => 'ready', 'label' => 'Ready for pickup'],
                ['value' => 'shipped', 'label' => 'Shipped'],
                ['value' => 'delivered', 'label' => 'Delivered'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
            'channels' => [
                ['value' => 'online', 'label' => 'Online'],
                ['value' => 'pos', 'label' => 'POS'],
            ],
            'sorts' => [
                ['value' => 'newest', 'label' => 'Newest'],
                ['value' => 'oldest', 'label' => 'Oldest'],
                ['value' => 'total_desc', 'label' => 'Total amount'],
                ['value' => 'status_asc', 'label' => 'Status'],
            ],
        ];
    }

    public function toAdminListPayload(Order $order): array
    {
        $paymentStatus = $this->resolvePaymentStatus($order);
        $fulfillmentStatus = $this->resolveFulfillmentStatus($order);

        return [
            'id' => (int) $order->id,
            'order_number' => $order->order_number,
            'channel' => $order->channel,
            'status' => $order->status,
            'status_label' => $this->statusLabel($order->status),
            'payment_status' => $paymentStatus,
            'payment_status_label' => $this->statusLabel($paymentStatus),
            'fulfillment_status' => $fulfillmentStatus,
            'fulfillment_status_label' => $this->statusLabel($fulfillmentStatus),
            'customer' => $order->user ? [
                'id' => (int) $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
                'phone' => $order->user->phone,
            ] : null,
            'total_amount' => (float) $order->total_amount,
            'currency' => $order->currency,
            'item_count' => (int) ($order->item_quantity ?? $order->items_count ?? 0),
            'payment_method' => $order->payments->sortByDesc(fn ($payment) => $payment->paid_at ?? $payment->created_at)->first()?->method,
            'tracking_number' => $order->shipment?->tracking_number,
            'courier_name' => $order->shipment?->courier_name,
            'created_at' => optional($order->created_at)?->toIso8601String(),
            'updated_at' => optional($order->updated_at)?->toIso8601String(),
        ];
    }

    public function toCustomerListPayload(Order $order): array
    {
        $paymentStatus = $this->resolvePaymentStatus($order);
        $fulfillmentStatus = $this->resolveFulfillmentStatus($order);

        return [
            'id' => (int) $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => $this->statusLabel($order->status),
            'payment_status' => $paymentStatus,
            'payment_status_label' => $this->statusLabel($paymentStatus),
            'fulfillment_status' => $fulfillmentStatus,
            'fulfillment_status_label' => $this->statusLabel($fulfillmentStatus),
            'total_amount' => (float) $order->total_amount,
            'currency' => $order->currency,
            'item_count' => (int) ($order->item_quantity ?? $order->items_count ?? 0),
            'created_at' => optional($order->created_at)?->toIso8601String(),
            'tracker' => $this->progressTracker($order),
        ];
    }

    public function adminDetailPayload(Order $order): array
    {
        $order = $this->loadDetailedOrder($order);

        return [
            ...$this->baseDetailPayload($order),
            'timeline' => $this->timelinePayload($order, false),
            'customer' => $order->user ? [
                'id' => (int) $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
                'phone' => $order->user->phone,
                'created_at' => optional($order->user->created_at)?->toIso8601String(),
            ] : null,
            'notes' => $order->notes->map(fn (OrderNote $note) => [
                'id' => (int) $note->id,
                'note' => $note->note,
                'author' => $note->user ? [
                    'id' => (int) $note->user->id,
                    'name' => $note->user->name,
                    'email' => $note->user->email,
                ] : null,
                'created_at' => optional($note->created_at)?->toIso8601String(),
            ])->values()->all(),
            'available_actions' => $this->availableActions($order),
            'notification_actions' => $this->notificationActions($order),
        ];
    }

    public function customerDetailPayload(Order $order): array
    {
        $order = $this->loadDetailedOrder($order);

        return $this->baseDetailPayload($order);
    }

    public function initializeOrderLifecycle(Order $order, ?int $actorId = null): void
    {
        $order->loadMissing(['payments', 'shipment']);

        if ($order->statusHistory()->exists()) {
            return;
        }

        $this->recordHistory(
            $order,
            self::HISTORY_TYPE_ORDER,
            null,
            $order->status,
            $actorId,
            'Order placed successfully.',
            true,
            ['channel' => $order->channel]
        );

        $paymentStatus = $this->resolvePaymentStatus($order);
        if ($paymentStatus !== 'pending') {
            $this->recordHistory(
                $order,
                self::HISTORY_TYPE_PAYMENT,
                null,
                $paymentStatus,
                $actorId,
                'Payment recorded for the order.',
                true
            );
        }

        $fulfillmentStatus = $this->resolveFulfillmentStatus($order);
        if ($order->shipment) {
            $this->recordHistory(
                $order,
                self::HISTORY_TYPE_FULFILLMENT,
                null,
                $fulfillmentStatus,
                $actorId,
                $order->shipment->type === 'pickup' ? 'Order is awaiting pickup preparation.' : 'Order is awaiting fulfillment.',
                true,
                ['shipment_type' => $order->shipment->type]
            );
        }

        $this->dispatchLifecycleEvent($order->id, 'placed', self::HISTORY_TYPE_ORDER, null, $order->status, $actorId, ['channel' => $order->channel]);
    }

    public function performAction(Order $order, string $action, array $payload, User $actor): Order
    {
        return DB::transaction(function () use ($order, $action, $payload, $actor) {
            /** @var Order $order */
            $order = Order::query()
                ->with(['payments', 'shipment', 'items.variant'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($order->shipment) {
                $shipment = Shipment::query()
                    ->whereKey($order->shipment->id)
                    ->lockForUpdate()
                    ->first();
                $order->setRelation('shipment', $shipment);
            }

            $paymentStatusBefore = $this->resolvePaymentStatus($order);
            $fulfillmentStatusBefore = $this->resolveFulfillmentStatus($order);

            match ($action) {
                self::ACTION_MARK_PAYMENT_PAID => $this->recordManualPayment($order, $payload, $actor, $paymentStatusBefore),
                self::ACTION_MARK_PROCESSING => $this->transitionFulfillment($order, 'processing', $actor, $payload, $fulfillmentStatusBefore),
                self::ACTION_MARK_PACKED => $this->transitionFulfillment($order, 'packed', $actor, $payload, $fulfillmentStatusBefore),
                self::ACTION_MARK_SHIPPED => $this->markShipped($order, $actor, $payload, $fulfillmentStatusBefore),
                self::ACTION_MARK_READY_FOR_PICKUP => $this->markReadyForPickup($order, $actor, $payload, $fulfillmentStatusBefore),
                self::ACTION_MARK_DELIVERED => $this->markDelivered($order, $actor, $payload, $fulfillmentStatusBefore),
                self::ACTION_CANCEL => $this->cancelOrder($order, $actor, $payload, $fulfillmentStatusBefore),
                default => throw ValidationException::withMessages(['action' => 'Unsupported order action.']),
            };

            $order->load([
                'user:id,name,email,phone',
                'items.variant.product.images',
                'items.variant.values.type',
                'payments.employee:id,name,email',
                'shipment.method',
                'shipment.pickup.location',
                'shipment.addresses.country',
                'shipment.addresses.state',
                'shipment.addresses.lga',
                'statusHistory.actor:id,name,email',
                'notes.user:id,name,email',
            ]);

            return $order;
        }, 3);
    }

    public function bulkAction(array $orderIds, string $action, array $payload, User $actor): array
    {
        $allowedActions = [
            self::ACTION_MARK_PROCESSING,
            self::ACTION_MARK_PACKED,
        ];

        if (!in_array($action, $allowedActions, true)) {
            throw ValidationException::withMessages([
                'action' => 'This bulk action is not supported.',
            ]);
        }

        $success = 0;
        $failed = [];

        Order::query()
            ->whereIn('id', $orderIds)
            ->orderBy('id')
            ->get()
            ->each(function (Order $order) use ($action, $payload, $actor, &$success, &$failed) {
                try {
                    $this->performAction($order, $action, $payload, $actor);
                    $success++;
                } catch (\Throwable $exception) {
                    $failed[] = [
                        'order_id' => (int) $order->id,
                        'order_number' => $order->order_number,
                        'message' => $exception instanceof ValidationException
                            ? collect($exception->errors())->flatten()->first()
                            : $exception->getMessage(),
                    ];
                }
            });

        return [
            'success_count' => $success,
            'failed' => $failed,
        ];
    }

    public function storeInternalNote(Order $order, User $actor, string $note): OrderNote
    {
        return $order->notes()->create([
            'user_id' => $actor->id,
            'note' => trim($note),
        ]);
    }

    public function resendNotification(Order $order, string $type): void
    {
        $order = $this->loadDetailedOrder($order);
        $user = $order->user;

        if (!$user) {
            return;
        }

        if ($type === 'placed') {
            $user->notify(new OrderPlacedNotification($order));
            return;
        }

        if ($type === 'shipped') {
            $user->notify(new OrderShippedNotification($order));
        }
    }

    public function availableActions(Order $order): array
    {
        $order->loadMissing(['payments', 'shipment']);

        $paymentStatus = $this->resolvePaymentStatus($order);
        $fulfillmentStatus = $this->resolveFulfillmentStatus($order);
        $shipmentType = $order->shipment?->type ?? 'delivery';

        $actions = [];

        if (!in_array($order->status, ['cancelled', 'completed'], true) && $paymentStatus !== 'paid') {
            $actions[] = [
                'key' => self::ACTION_MARK_PAYMENT_PAID,
                'label' => 'Record payment',
                'description' => 'Add a paid transaction record and move the order into the paid state when fully covered.',
            ];
        }

        if ($order->shipment && !in_array($order->status, ['cancelled', 'completed'], true) && $paymentStatus === 'paid') {
            if ($fulfillmentStatus === 'pending') {
                $actions[] = [
                    'key' => self::ACTION_MARK_PROCESSING,
                    'label' => 'Mark processing',
                    'description' => 'Move the order into active preparation.',
                ];
            }

            if (in_array($fulfillmentStatus, ['pending', 'processing'], true)) {
                $actions[] = [
                    'key' => self::ACTION_MARK_PACKED,
                    'label' => 'Mark packed',
                    'description' => 'Confirm the order has been packed and is ready for dispatch.',
                ];
            }

            if ($shipmentType === 'pickup' && in_array($fulfillmentStatus, ['pending', 'processing', 'packed'], true)) {
                $actions[] = [
                    'key' => self::ACTION_MARK_READY_FOR_PICKUP,
                    'label' => 'Ready for pickup',
                    'description' => 'Notify the customer that the order is ready for collection.',
                ];
            }

            if ($shipmentType !== 'pickup' && in_array($fulfillmentStatus, ['pending', 'processing', 'packed'], true)) {
                $actions[] = [
                    'key' => self::ACTION_MARK_SHIPPED,
                    'label' => 'Mark shipped',
                    'description' => 'Move the order into transit and optionally attach tracking details.',
                ];
            }

            if (($shipmentType === 'pickup' && $fulfillmentStatus === 'ready') || ($shipmentType !== 'pickup' && $fulfillmentStatus === 'shipped')) {
                $actions[] = [
                    'key' => self::ACTION_MARK_DELIVERED,
                    'label' => $shipmentType === 'pickup' ? 'Mark collected' : 'Mark delivered',
                    'description' => 'Complete the order once the customer receives it.',
                ];
            }
        }

        if (!in_array($order->status, ['cancelled', 'completed', 'shipped'], true) && !in_array($fulfillmentStatus, ['shipped', 'ready', 'delivered', 'completed', 'cancelled'], true)) {
            $actions[] = [
                'key' => self::ACTION_CANCEL,
                'label' => 'Cancel order',
                'description' => 'Cancel the order and restock reserved inventory movements.',
                'danger' => true,
            ];
        }

        return $actions;
    }

    public function notificationActions(Order $order): array
    {
        $actions = [
            ['key' => 'placed', 'label' => 'Resend order confirmation'],
        ];

        if (in_array($this->resolveFulfillmentStatus($order), ['shipped', 'ready', 'delivered', 'completed'], true)) {
            $actions[] = ['key' => 'shipped', 'label' => 'Resend shipment update'];
        }

        return $actions;
    }

    public function resolvePaymentStatus(Order $order): string
    {
        $payments = $order->relationLoaded('payments') ? $order->payments : $order->payments()->get();

        if ($payments->contains(fn ($payment) => $payment->status === 'refunded')) {
            return 'refunded';
        }

        $paidAmount = (float) $payments->where('status', 'paid')->sum('amount');
        $failedCount = (int) $payments->where('status', 'failed')->count();

        if ($paidAmount >= (float) $order->total_amount && (float) $order->total_amount > 0) {
            return 'paid';
        }

        if ($paidAmount > 0) {
            return 'partially_paid';
        }

        if ($failedCount > 0 && $payments->isNotEmpty()) {
            return 'failed';
        }

        return 'pending';
    }

    public function resolveFulfillmentStatus(Order $order): string
    {
        $shipment = $order->relationLoaded('shipment') ? $order->shipment : $order->shipment()->first();

        if ($shipment?->status) {
            return (string) $shipment->status;
        }

        return match ($order->status) {
            'completed' => 'delivered',
            'shipped' => 'shipped',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    public function progressTracker(Order $order): array
    {
        $order->loadMissing(['shipment', 'payments', 'statusHistory']);

        $paymentStatus = $this->resolvePaymentStatus($order);
        $fulfillmentStatus = $this->resolveFulfillmentStatus($order);
        $isPickup = $order->shipment?->type === 'pickup';
        $isCancelled = $order->status === 'cancelled' || $fulfillmentStatus === 'cancelled';

        $steps = [
            [
                'key' => 'placed',
                'label' => 'Order placed',
                'status' => 'complete',
                'timestamp' => optional($order->created_at)?->toIso8601String(),
            ],
            [
                'key' => 'payment_confirmed',
                'label' => 'Payment confirmed',
                'status' => in_array($paymentStatus, ['paid', 'refunded'], true) ? 'complete' : ($paymentStatus === 'partially_paid' ? 'current' : 'upcoming'),
                'timestamp' => optional($order->payments->where('status', 'paid')->sortBy('paid_at')->first()?->paid_at)?->toIso8601String(),
            ],
            [
                'key' => 'processing',
                'label' => 'Processing',
                'status' => in_array($fulfillmentStatus, ['processing', 'packed', 'shipped', 'ready', 'delivered', 'completed'], true) ? 'complete' : (in_array($paymentStatus, ['paid', 'refunded'], true) ? 'current' : 'upcoming'),
                'timestamp' => $this->historyTimestamp($order, self::HISTORY_TYPE_FULFILLMENT, ['processing', 'packed']),
            ],
            [
                'key' => $isPickup ? 'ready' : 'shipped',
                'label' => $isPickup ? 'Ready for pickup' : 'Shipped',
                'status' => ($isPickup && in_array($fulfillmentStatus, ['ready', 'completed'], true)) || (!$isPickup && in_array($fulfillmentStatus, ['shipped', 'delivered'], true)) ? 'complete' : (in_array($fulfillmentStatus, ['processing', 'packed'], true) ? 'current' : 'upcoming'),
                'timestamp' => $isPickup
                    ? optional($order->shipment?->ready_at)?->toIso8601String()
                    : optional($order->shipment?->shipped_at)?->toIso8601String(),
            ],
            [
                'key' => $isPickup ? 'collected' : 'delivered',
                'label' => $isPickup ? 'Collected' : 'Delivered',
                'status' => in_array($fulfillmentStatus, ['delivered', 'completed'], true) || $order->status === 'completed' ? 'complete' : 'upcoming',
                'timestamp' => optional($order->shipment?->delivered_at ?? $this->historyTimestampCarbon($order, self::HISTORY_TYPE_ORDER, ['completed']))?->toIso8601String(),
            ],
        ];

        return [
            'steps' => $steps,
            'state' => $isCancelled
                ? [
                    'kind' => 'cancelled',
                    'label' => 'Order cancelled',
                    'description' => 'This order has been cancelled and will not move further through fulfillment.',
                ]
                : ($paymentStatus === 'refunded'
                    ? [
                        'kind' => 'refunded',
                        'label' => 'Payment refunded',
                        'description' => 'A refund has been recorded for this order.',
                    ]
                    : null),
        ];
    }

    protected function baseDetailPayload(Order $order): array
    {
        $paymentStatus = $this->resolvePaymentStatus($order);
        $fulfillmentStatus = $this->resolveFulfillmentStatus($order);
        $addresses = $order->shipment?->addresses ?? collect();

        return [
            'id' => (int) $order->id,
            'order_number' => $order->order_number,
            'channel' => $order->channel,
            'status' => $order->status,
            'status_label' => $this->statusLabel($order->status),
            'payment_status' => $paymentStatus,
            'payment_status_label' => $this->statusLabel($paymentStatus),
            'fulfillment_status' => $fulfillmentStatus,
            'fulfillment_status_label' => $this->statusLabel($fulfillmentStatus),
            'subtotal' => (float) $order->subtotal,
            'shipping_total' => (float) $order->shipping_total,
            'tax_total' => (float) $order->tax_total,
            'discount' => (float) $order->discount,
            'total_amount' => (float) $order->total_amount,
            'currency' => $order->currency,
            'created_at' => optional($order->created_at)?->toIso8601String(),
            'updated_at' => optional($order->updated_at)?->toIso8601String(),
            'tracker' => $this->progressTracker($order),
            'timeline' => $this->timelinePayload($order, true),
            'items' => $order->items->map(function ($item) {
                $variant = $item->variant;
                $product = $variant?->product;

                return [
                    'id' => (int) $item->id,
                    'quantity' => (int) $item->quantity,
                    'price' => (float) $item->price,
                    'subtotal' => round((float) $item->price * (int) $item->quantity, 2),
                    'product' => [
                        'id' => $product?->id,
                        'name' => $product?->name,
                        'slug' => $product?->slug,
                        'image' => $product && $variant ? $this->productService->resolveProductImage($product, $variant) : null,
                    ],
                    'variant' => [
                        'id' => $variant?->id,
                        'sku' => $variant?->sku,
                        'label' => $variant?->values?->map(fn ($value) => trim(($value->type?->name ? $value->type->name . ': ' : '') . $value->value))->implode(' / '),
                    ],
                ];
            })->values()->all(),
            'payments' => $order->payments->map(fn ($payment) => [
                'id' => (int) $payment->id,
                'method' => $payment->method,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'status_label' => $this->statusLabel($payment->status),
                'paid_at' => optional($payment->paid_at)?->toIso8601String(),
                'reference' => $payment->transaction_reference,
                'recorded_by' => $payment->employee ? [
                    'id' => (int) $payment->employee->id,
                    'name' => $payment->employee->name,
                    'email' => $payment->employee->email,
                ] : null,
            ])->values()->all(),
            'payment_summary' => [
                'status' => $paymentStatus,
                'status_label' => $this->statusLabel($paymentStatus),
                'paid_amount' => (float) $order->payments->where('status', 'paid')->sum('amount'),
                'refunded_amount' => (float) $order->payments->where('status', 'refunded')->sum('amount'),
                'outstanding_amount' => max(0, round((float) $order->total_amount - (float) $order->payments->where('status', 'paid')->sum('amount'), 2)),
            ],
            'shipment' => $order->shipment ? [
                'status' => $order->shipment->status,
                'status_label' => $this->statusLabel($order->shipment->status),
                'type' => $order->shipment->type,
                'type_label' => $this->statusLabel($order->shipment->type),
                'method' => $order->shipment->method?->name,
                'courier_name' => $order->shipment->courier_name,
                'tracking_number' => $order->shipment->tracking_number,
                'tracking_url' => $order->shipment->tracking_url,
                'ready_at' => optional($order->shipment->ready_at)?->toIso8601String(),
                'shipped_at' => optional($order->shipment->shipped_at)?->toIso8601String(),
                'delivered_at' => optional($order->shipment->delivered_at)?->toIso8601String(),
                'pickup' => $order->shipment->pickup ? [
                    'reference' => $order->shipment->pickup->reference,
                    'contact_name' => $order->shipment->pickup->contact_name,
                    'contact_phone' => $order->shipment->pickup->contact_phone,
                    'location' => $order->shipment->pickup->location ? [
                        'name' => $order->shipment->pickup->location->name,
                        'address_line1' => $order->shipment->pickup->location->address_line1,
                        'address_line2' => $order->shipment->pickup->location->address_line2,
                    ] : null,
                ] : null,
                'addresses' => $addresses->map(fn ($address) => $this->mapAddress($address))->values()->all(),
            ] : null,
            'billing_address' => $addresses->firstWhere('type', 'billing') ? $this->mapAddress($addresses->firstWhere('type', 'billing')) : null,
            'shipping_address' => $addresses->firstWhere('type', 'shipping') ? $this->mapAddress($addresses->firstWhere('type', 'shipping')) : null,
        ];
    }

    protected function loadDetailedOrder(Order $order): Order
    {
        $order->load([
            'user:id,name,email,phone,created_at',
            'items.variant.product.images',
            'items.variant.values.type',
            'payments.employee:id,name,email',
            'shipment.method',
            'shipment.pickup.location',
            'shipment.addresses.country',
            'shipment.addresses.state',
            'shipment.addresses.lga',
            'statusHistory.actor:id,name,email',
            'notes.user:id,name,email',
        ]);

        return $order;
    }

    protected function applyAdminFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $orderNumber = trim((string) ($filters['order_number'] ?? ''));
        $customer = trim((string) ($filters['customer'] ?? ''));
        $paymentStatus = trim((string) ($filters['payment_status'] ?? ''));
        $orderStatus = trim((string) ($filters['order_status'] ?? ''));
        $fulfillmentStatus = trim((string) ($filters['fulfillment_status'] ?? ''));
        $channel = trim((string) ($filters['channel'] ?? ''));
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('orders.order_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('payments', function (Builder $paymentQuery) use ($search) {
                        $paymentQuery->where('transaction_reference', 'like', "%{$search}%");
                    });
            });
        }

        if ($orderNumber !== '') {
            $query->where('orders.order_number', 'like', "%{$orderNumber}%");
        }

        if ($customer !== '') {
            $query->whereHas('user', function (Builder $userQuery) use ($customer) {
                $userQuery->where('name', 'like', "%{$customer}%")
                    ->orWhere('email', 'like', "%{$customer}%")
                    ->orWhere('phone', 'like', "%{$customer}%");
            });
        }

        if ($orderStatus !== '') {
            $query->where('orders.status', $orderStatus);
        }

        if ($paymentStatus !== '') {
            $this->applyPaymentStatusFilter($query, $paymentStatus);
        }

        if ($fulfillmentStatus !== '') {
            $this->applyFulfillmentFilter($query, $fulfillmentStatus);
        }

        if ($channel !== '') {
            $query->where('orders.channel', $channel);
        }

        if ($from !== '') {
            $query->whereDate('orders.created_at', '>=', $from);
        }

        if ($to !== '') {
            $query->whereDate('orders.created_at', '<=', $to);
        }
    }

    protected function applyAdminSorting(Builder $query, array $filters): void
    {
        $sort = (string) ($filters['sort'] ?? 'newest');

        match ($sort) {
            'oldest' => $query->oldest('orders.created_at'),
            'total_desc' => $query->orderByDesc('orders.total_amount')->latest('orders.created_at'),
            'total_asc' => $query->orderBy('orders.total_amount')->latest('orders.created_at'),
            'status_asc' => $query->orderBy('orders.status')->latest('orders.created_at'),
            'status_desc' => $query->orderByDesc('orders.status')->latest('orders.created_at'),
            default => $query->latest('orders.created_at'),
        };
    }

    protected function applyPaymentStatusFilter(Builder $query, string $paymentStatus): void
    {
        $paidSql = "(select coalesce(sum(amount), 0) from payments where payments.payable_type = ? and payments.payable_id = orders.id and payments.status = 'paid')";
        $refundedSql = "exists(select 1 from payments where payments.payable_type = ? and payments.payable_id = orders.id and payments.status = 'refunded')";
        $failedSql = "exists(select 1 from payments where payments.payable_type = ? and payments.payable_id = orders.id and payments.status = 'failed')";

        match ($paymentStatus) {
            'paid' => $query->whereRaw("{$paidSql} >= orders.total_amount", [Order::class]),
            'partially_paid' => $query
                ->whereRaw("{$paidSql} > 0", [Order::class])
                ->whereRaw("{$paidSql} < orders.total_amount", [Order::class]),
            'refunded' => $query->whereRaw($refundedSql, [Order::class]),
            'failed' => $query->whereRaw($failedSql, [Order::class]),
            default => $query
                ->whereRaw("{$paidSql} = 0", [Order::class])
                ->whereRaw("not {$refundedSql}", [Order::class]),
        };
    }

    protected function applyFulfillmentFilter(Builder $query, string $fulfillmentStatus): void
    {
        match ($fulfillmentStatus) {
            'pending' => $query->where(function (Builder $builder) {
                $builder->whereDoesntHave('shipment')
                    ->orWhereHas('shipment', fn (Builder $shipment) => $shipment->where('status', 'pending'));
            }),
            'shipped' => $query->whereHas('shipment', fn (Builder $shipment) => $shipment->where('status', 'shipped')),
            'delivered' => $query->whereHas('shipment', fn (Builder $shipment) => $shipment->whereIn('status', ['delivered', 'completed'])),
            default => $query->whereHas('shipment', fn (Builder $shipment) => $shipment->where('status', $fulfillmentStatus)),
        };
    }

    protected function recordManualPayment(Order $order, array $payload, User $actor, string $paymentStatusBefore): void
    {
        if (in_array($order->status, ['cancelled', 'completed'], true)) {
            throw ValidationException::withMessages([
                'action' => 'This order cannot accept manual payment updates in its current state.',
            ]);
        }

        $amount = isset($payload['payment_amount'])
            ? round((float) $payload['payment_amount'], 2)
            : round($order->outstandingBalance(), 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'payment_amount' => 'There is no outstanding balance left to mark as paid.',
            ]);
        }

        $method = (string) ($payload['payment_method'] ?? 'transfer');
        if (!in_array($method, self::PAYMENT_METHODS, true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Unsupported payment method supplied.',
            ]);
        }

        $order->addPayment([
            'type' => 'inflow',
            'method' => $method,
            'amount' => $amount,
            'status' => 'paid',
            'paid_at' => now(),
            'employee_id' => $actor->id,
            'transaction_reference' => $payload['transaction_reference'] ?? null,
            'meta' => [
                'source' => 'admin_order_management',
                'note' => $payload['note'] ?? null,
            ],
        ]);

        $order->load('payments');
        $paymentStatusAfter = $this->resolvePaymentStatus($order);

        if ($paymentStatusAfter !== $paymentStatusBefore) {
            $this->recordHistory(
                $order,
                self::HISTORY_TYPE_PAYMENT,
                $paymentStatusBefore,
                $paymentStatusAfter,
                $actor->id,
                $payload['note'] ?? 'Payment status updated manually.',
                true
            );
        }

        if ($order->status === 'pending' && $paymentStatusAfter === 'paid') {
            $this->changeOrderStatus($order, 'paid', $actor->id, $payload['note'] ?? 'Order marked paid after manual payment entry.');
        }
    }

    protected function transitionFulfillment(Order $order, string $targetStatus, User $actor, array $payload, string $fulfillmentStatusBefore): void
    {
        $shipment = $this->requireShipment($order);
        $paymentStatus = $this->resolvePaymentStatus($order);

        if ($paymentStatus !== 'paid') {
            throw ValidationException::withMessages([
                'action' => 'Only paid orders can move through fulfillment.',
            ]);
        }

        if (in_array($shipment->status, ['cancelled', 'delivered', 'completed', 'ready', 'shipped'], true) && in_array($targetStatus, ['processing', 'packed'], true)) {
            throw ValidationException::withMessages([
                'action' => 'This fulfillment step is no longer valid for the current shipment state.',
            ]);
        }

        $this->changeShipmentStatus($order, $shipment, $targetStatus, $actor->id, $payload['note'] ?? null, $fulfillmentStatusBefore);
    }

    protected function markShipped(Order $order, User $actor, array $payload, string $fulfillmentStatusBefore): void
    {
        $shipment = $this->requireShipment($order);

        if ($shipment->type === 'pickup') {
            throw ValidationException::withMessages([
                'action' => 'Pickup shipments should be marked ready for pickup instead of shipped.',
            ]);
        }

        if (in_array($shipment->status, ['cancelled', 'delivered', 'completed', 'shipped'], true)) {
            throw ValidationException::withMessages([
                'action' => 'This order cannot be marked as shipped from its current state.',
            ]);
        }

        $shipment->fill([
            'courier_name' => $payload['courier_name'] ?? $shipment->courier_name,
            'tracking_number' => $payload['tracking_number'] ?? $shipment->tracking_number,
            'tracking_url' => $payload['tracking_url'] ?? $shipment->tracking_url,
            'shipped_at' => $shipment->shipped_at ?? now(),
        ]);
        $shipment->save();

        $this->changeShipmentStatus($order, $shipment, 'shipped', $actor->id, $payload['note'] ?? null, $fulfillmentStatusBefore);
        $this->changeOrderStatus($order, 'shipped', $actor->id, $payload['note'] ?? 'Order marked as shipped.');

        $this->dispatchLifecycleEvent($order->id, 'status_changed', self::HISTORY_TYPE_FULFILLMENT, $fulfillmentStatusBefore, 'shipped', $actor->id, ['shipment_type' => $shipment->type]);
    }

    protected function markReadyForPickup(Order $order, User $actor, array $payload, string $fulfillmentStatusBefore): void
    {
        $shipment = $this->requireShipment($order);

        if ($shipment->type !== 'pickup') {
            throw ValidationException::withMessages([
                'action' => 'Only pickup shipments can be marked ready for pickup.',
            ]);
        }

        if (in_array($shipment->status, ['cancelled', 'delivered', 'completed', 'ready'], true)) {
            throw ValidationException::withMessages([
                'action' => 'This pickup order is not eligible for the ready state.',
            ]);
        }

        $shipment->fill([
            'ready_at' => $shipment->ready_at ?? now(),
        ]);
        $shipment->save();

        $this->changeShipmentStatus($order, $shipment, 'ready', $actor->id, $payload['note'] ?? null, $fulfillmentStatusBefore);
        $this->changeOrderStatus($order, 'shipped', $actor->id, $payload['note'] ?? 'Order is ready for pickup.');

        $this->dispatchLifecycleEvent($order->id, 'status_changed', self::HISTORY_TYPE_FULFILLMENT, $fulfillmentStatusBefore, 'ready', $actor->id, ['shipment_type' => $shipment->type]);
    }

    protected function markDelivered(Order $order, User $actor, array $payload, string $fulfillmentStatusBefore): void
    {
        $shipment = $this->requireShipment($order);
        $targetStatus = $shipment->type === 'pickup' ? 'completed' : 'delivered';

        if (in_array($shipment->status, ['cancelled', 'delivered', 'completed'], true)) {
            throw ValidationException::withMessages([
                'action' => 'This order is already closed and cannot be delivered again.',
            ]);
        }

        if ($shipment->type === 'pickup' && $shipment->status !== 'ready') {
            throw ValidationException::withMessages([
                'action' => 'Pickup orders must be marked ready before completion.',
            ]);
        }

        if ($shipment->type !== 'pickup' && $shipment->status !== 'shipped') {
            throw ValidationException::withMessages([
                'action' => 'Delivery orders must be shipped before they can be delivered.',
            ]);
        }

        $shipment->fill([
            'delivered_at' => $shipment->delivered_at ?? now(),
        ]);
        $shipment->save();

        $this->changeShipmentStatus($order, $shipment, $targetStatus, $actor->id, $payload['note'] ?? null, $fulfillmentStatusBefore);
        $this->changeOrderStatus($order, 'completed', $actor->id, $payload['note'] ?? 'Order completed.');
    }

    protected function cancelOrder(Order $order, User $actor, array $payload, string $fulfillmentStatusBefore): void
    {
        if (in_array($order->status, ['cancelled', 'completed', 'shipped'], true)) {
            throw ValidationException::withMessages([
                'action' => 'This order can no longer be cancelled.',
            ]);
        }

        if (in_array($fulfillmentStatusBefore, ['shipped', 'ready', 'delivered', 'completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'action' => 'This order can no longer be cancelled because fulfillment has already advanced too far.',
            ]);
        }

        $this->changeOrderStatus($order, 'cancelled', $actor->id, $payload['note'] ?? 'Order cancelled.');

        if ($order->shipment) {
            $this->changeShipmentStatus($order, $order->shipment, 'cancelled', $actor->id, $payload['note'] ?? null, $fulfillmentStatusBefore);
        }

        $this->restockOrderItems($order, $actor);
    }

    protected function requireShipment(Order $order): Shipment
    {
        $shipment = $order->shipment;

        if (!$shipment) {
            throw ValidationException::withMessages([
                'action' => 'This order does not have a shipment record to manage.',
            ]);
        }

        return $shipment;
    }

    protected function changeOrderStatus(Order $order, string $newStatus, ?int $actorId, ?string $note = null): void
    {
        if ($order->status === $newStatus) {
            return;
        }

        $previous = $order->status;
        $order->status = $newStatus;
        $order->save();

        $this->recordHistory($order, self::HISTORY_TYPE_ORDER, $previous, $newStatus, $actorId, $note, true);
    }

    protected function changeShipmentStatus(Order $order, Shipment $shipment, string $newStatus, ?int $actorId, ?string $note = null, ?string $previousStatus = null): void
    {
        $previousStatus ??= $shipment->status;

        if ($shipment->status !== $newStatus) {
            $shipment->status = $newStatus;
            $shipment->save();
        }

        if ($previousStatus !== $newStatus) {
            $this->recordHistory(
                $order,
                self::HISTORY_TYPE_FULFILLMENT,
                $previousStatus,
                $newStatus,
                $actorId,
                $note,
                true,
                ['shipment_type' => $shipment->type]
            );
        }
    }

    protected function restockOrderItems(Order $order, User $actor): void
    {
        $stockOutEntries = StockEntry::query()
            ->where('source_type', Order::class)
            ->where('source_id', $order->id)
            ->where('type', 'stock_out')
            ->get()
            ->keyBy('variant_id');

        foreach ($order->items as $item) {
            $stockOutEntry = $stockOutEntries->get($item->variant_id);
            $unitCost = (float) ($stockOutEntry?->unit_cost ?? $item->variant?->average_cost ?? 0);

            $this->inventoryService->stockIn([
                'variant_id' => (int) $item->variant_id,
                'quantity' => (int) $item->quantity,
                'unit_cost' => $unitCost,
                'employee_id' => $actor->id,
                'reason' => 'Order cancellation restock',
                'source_type' => Order::class,
                'source_id' => $order->id,
                'note' => "Restocked from cancelled order {$order->order_number}",
            ]);
        }
    }

    protected function recordHistory(
        Order $order,
        string $statusType,
        ?string $previousStatus,
        string $newStatus,
        ?int $actorId,
        ?string $note = null,
        bool $customerVisible = true,
        array $meta = []
    ): OrderStatusHistory {
        return $order->statusHistory()->create([
            'status_type' => $statusType,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'note' => $note,
            'changed_by' => $actorId,
            'customer_visible' => $customerVisible,
            'meta' => $meta ?: null,
        ]);
    }

    protected function dispatchLifecycleEvent(
        int $orderId,
        string $event,
        string $statusType,
        ?string $previousStatus,
        ?string $newStatus,
        ?int $actorId = null,
        array $meta = []
    ): void {
        DB::afterCommit(function () use ($orderId, $event, $statusType, $previousStatus, $newStatus, $actorId, $meta) {
            $order = Order::query()
                ->with([
                    'user:id,name,email,phone',
                    'items.variant.product.images',
                    'items.variant.values.type',
                    'payments.employee:id,name,email',
                    'shipment.method',
                    'shipment.pickup.location',
                    'shipment.addresses.country',
                    'shipment.addresses.state',
                    'shipment.addresses.lga',
                ])
                ->find($orderId);

            if ($order) {
                event(new OrderLifecycleChanged($order, $event, $statusType, $previousStatus, $newStatus, $actorId, $meta));
            }
        });
    }

    protected function timelinePayload(Order $order, bool $customerVisibleOnly): array
    {
        $history = $order->statusHistory;

        if ($customerVisibleOnly) {
            $history = $history->where('customer_visible', true);
        }

        return $history
            ->map(fn (OrderStatusHistory $entry) => [
                'id' => (int) $entry->id,
                'status_type' => $entry->status_type,
                'status_type_label' => $this->statusLabel($entry->status_type),
                'previous_status' => $entry->previous_status,
                'previous_status_label' => $entry->previous_status ? $this->statusLabel($entry->previous_status) : null,
                'new_status' => $entry->new_status,
                'new_status_label' => $this->statusLabel($entry->new_status),
                'note' => $entry->note,
                'customer_visible' => (bool) $entry->customer_visible,
                'meta' => $entry->meta,
                'changed_by' => $entry->actor ? [
                    'id' => (int) $entry->actor->id,
                    'name' => $entry->actor->name,
                    'email' => $entry->actor->email,
                ] : null,
                'created_at' => optional($entry->created_at)?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    protected function historyTimestamp(Order $order, string $statusType, array $statuses): ?string
    {
        return optional($this->historyTimestampCarbon($order, $statusType, $statuses))?->toIso8601String();
    }

    protected function historyTimestampCarbon(Order $order, string $statusType, array $statuses)
    {
        $entry = $order->statusHistory
            ->where('status_type', $statusType)
            ->first(fn (OrderStatusHistory $history) => in_array($history->new_status, $statuses, true));

        return $entry?->created_at;
    }

    protected function mapAddress($address): array
    {
        return [
            'type' => $address->type,
            'type_label' => $this->statusLabel($address->type),
            'name' => $address->name,
            'phone' => $address->phone,
            'email' => $address->email,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'postal_code' => $address->postal_code,
            'state' => $address->state?->name,
            'lga' => $address->lga?->name,
            'country' => $address->country?->name,
        ];
    }

    protected function statusLabel(?string $status): ?string
    {
        return $status ? Str::of($status)->replace('_', ' ')->headline()->value() : null;
    }
}
