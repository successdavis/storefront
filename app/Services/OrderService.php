<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pickup;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OrderService
{
    protected float $moneyTolerance = 0.01;

    public function __construct(
        protected InventoryService $inventoryService,
        protected PricingQuoteService $pricingQuoteService,
        protected DiscountService $discountService,
        protected StockReservationService $stockReservationService,
        protected OrderManagementService $orderManagementService,
        protected CustomerInvoiceService $customerInvoiceService,
        protected AccountingService $accountingService,
        protected DropshippingService $dropshippingService,
    ) {}

    /**
     * @throws \Throwable
     */
    public function handle(array $payload): Order
    {
        $this->validatePayload($payload);
        $channel = $this->resolveChannel($payload);

        return DB::transaction(function () use ($payload, $channel) {
            $session = $this->getValidCheckoutSession((string) $payload['checkout_token']);

            if (!empty($session->order_id)) {
                return Order::query()->findOrFail((int) $session->order_id);
            }

            [$userId, $user] = $this->resolveUser($payload, $channel);

            if ($channel === 'online') {
                $this->stockReservationService->consumeForSession((int) $session->id);
            }

            $items = $channel === 'pos'
                ? $this->resolvePosOrderItems($payload, $session, $user)
                : $this->resolveOnlineOrderItems($session);

            $this->lockAndValidateStock($items);

            $order = $this->createOrderFromSession($session, $payload, $userId, $channel);

            $this->createOrderItemsAndAdjustInventory($order, $items, $channel);
            $this->handlePosSale($order, $userId, $payload, $channel);
            $this->handleShipping($order, $payload, $session, $userId);
            $this->recordPayment($order, $payload, (float) $session->total, $channel);
            $this->syncReceivable($order, $payload, $channel);
            $this->finalizeOrderStatus($order, $channel);
            $this->commitDiscount($order, $session);
            $this->markSessionUsed($session, $order);
            $this->orderManagementService->initializeOrderLifecycle($order, auth()->id());
            $this->accountingService->postOrder(
                $order,
                $this->primaryPaymentMethod($payload),
                auth()->id(),
            );
            $this->logOrder($order);

            return $order;
        }, 5);
    }

    private function validatePayload(array $payload): void
    {
        if (empty($payload['checkout_token']) || !is_string($payload['checkout_token'])) {
            throw ValidationException::withMessages([
                'checkout_token' => 'Missing checkout token.',
            ]);
        }
    }

    private function resolveChannel(array $payload): string
    {
        return isset($payload['channel']) && strtolower((string) $payload['channel']) === 'pos'
            ? 'pos'
            : 'online';
    }

    private function getValidCheckoutSession(string $token)
    {
        $session = DB::table('checkout_sessions')
            ->where('token', $token)
            ->lockForUpdate()
            ->first();

        if (!$session) {
            throw ValidationException::withMessages([
                'checkout_token' => 'Invalid or expired checkout token.',
            ]);
        }

        if (!empty($session->order_id)) {
            return $session;
        }

        if ((bool) $session->used) {
            throw ValidationException::withMessages([
                'checkout_token' => 'This checkout session has already been used.',
            ]);
        }

        if (
            $session->expires_at
            && \Carbon\Carbon::parse($session->expires_at)->isPast()
            && strtolower((string) ($session->payment_status ?? 'pending')) !== 'paid'
        ) {
            throw ValidationException::withMessages([
                'checkout_token' => 'Checkout session expired. Please refresh checkout.',
            ]);
        }

        return $session;
    }

    private function resolveUser(array $payload, string $channel): array
    {
        $userId = $this->resolveUserId($payload, $channel);
        $user = $userId ? User::find($userId) : null;

        return [$userId, $user];
    }

    private function resolvePosOrderItems(array $payload, object $session, ?User $user): array
    {
        if (empty($payload['items']) || !is_array($payload['items'])) {
            throw ValidationException::withMessages([
                'items' => 'POS order items are required.',
            ]);
        }

        $quote = $this->pricingQuoteService->quote([
            'items' => collect($payload['items'])->map(fn (array $line) => [
                'variant_id' => (int) ($line['variant_id'] ?? 0),
                'quantity' => (float) ($line['quantity'] ?? 0),
            ])->values()->all(),
            'shipping' => $payload['shipping'] ?? [],
            'coupon' => $payload['coupon'] ?? data_get(json_decode((string) $session->discount_snapshot, true), 'code'),
            'channel' => 'pos',
            'user' => $user,
            'tax_total' => (float) ($payload['tax_total'] ?? 0),
        ]);

        $this->assertSessionIntegrity(
            $session,
            (float) $quote['summary']['subtotal'],
            (float) $quote['summary']['shipping_total'],
            (float) $quote['summary']['discount_amount'],
            (float) $quote['summary']['total'],
            'pos'
        );

        return collect($quote['items'])->map(fn (array $line) => [
            'variant_id' => (int) $line['variant_id'],
            'quantity' => (float) $line['quantity'],
            'unit_price' => (float) $line['unit_price'],
            'line_total' => (float) $line['line_total'],
            'fulfillment_type' => $line['fulfillment_type'] ?? ProductVariant::FULFILLMENT_STOCKED,
            'supplier_id' => $line['supplier_id'] ?? null,
            'supplier_cost' => $line['supplier_cost'] ?? null,
            'supplier_lead_time_days' => $line['supplier_lead_time_days'] ?? null,
        ])->values()->all();
    }

    private function resolveOnlineOrderItems(object $session): array
    {
        $sessionItems = json_decode((string) $session->items, true) ?? [];
        if (empty($sessionItems)) {
            throw ValidationException::withMessages([
                'checkout_token' => 'Checkout session does not contain items.',
            ]);
        }

        return collect($sessionItems)->map(function (array $line) {
            $variantId = (int) ($line['variant_id'] ?? 0);
            $quantity = (float) ($line['quantity'] ?? 0);
            $unitPrice = round((float) ($line['unit_price'] ?? $line['price'] ?? 0), 2);

            if ($variantId <= 0 || $quantity <= 0) {
                throw ValidationException::withMessages([
                    'checkout_token' => 'Invalid item snapshot in checkout session.',
                ]);
            }

            return [
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round($unitPrice * $quantity, 2),
                'fulfillment_type' => (string) ($line['fulfillment_type'] ?? ProductVariant::FULFILLMENT_STOCKED),
                'supplier_id' => !empty($line['supplier_id']) ? (int) $line['supplier_id'] : null,
                'supplier_cost' => array_key_exists('supplier_cost', $line) && $line['supplier_cost'] !== null ? (float) $line['supplier_cost'] : null,
                'supplier_lead_time_days' => !empty($line['supplier_lead_time_days']) ? (int) $line['supplier_lead_time_days'] : null,
            ];
        })->values()->all();
    }

    private function assertSessionIntegrity($session, float $subtotal, float $shipping, float $discount, float $total, string $channel): void
    {
        $tol = $this->discountService->getMoneyTolerance();

        if (
            abs((float) $session->subtotal - round($subtotal, 2)) > $tol ||
            abs((float) $session->shipping_total - round($shipping, 2)) > $tol ||
            abs((float) $session->discount_amount - round($discount, 2)) > $tol ||
            abs((float) $session->total - round($total, 2)) > $tol
        ) {
            if ($channel === 'online') {
                Log::warning('Checkout session mismatch after payment - honoring session snapshot', [
                    'session_id' => $session->id,
                    'expected' => [
                        'subtotal' => (float) $session->subtotal,
                        'shipping' => (float) $session->shipping_total,
                        'discount' => (float) $session->discount_amount,
                        'total' => (float) $session->total,
                    ],
                    'computed' => compact('subtotal', 'shipping', 'discount', 'total'),
                ]);

                return;
            }

            throw ValidationException::withMessages([
                'checkout' => 'Pricing changed since checkout preview. Please refresh checkout and continue.',
            ]);
        }
    }

    private function createOrderFromSession($session, array $payload, ?int $userId, string $channel): Order
    {
        return Order::create([
            'user_id' => $userId,
            'subtotal' => (float) $session->subtotal,
            'tax_total' => (float) ($payload['tax_total'] ?? 0),
            'discount' => (float) $session->discount_amount,
            'currency' => $payload['currency'] ?? ($session->payment_currency ?? 'NGN'),
            'channel' => $channel,
            'order_number' => $this->generateOrderNumber($channel),
            'status' => $payload['status'] ?? ($channel === 'online' ? 'paid' : 'pending'),
            'total_amount' => (float) $session->total,
            'shipping_total' => (float) $session->shipping_total,
        ]);
    }

    private function createOrderItemsAndAdjustInventory(Order $order, array $items, string $channel): void
    {
        $variants = ProductVariant::query()
            ->whereIn('id', collect($items)->pluck('variant_id')->all())
            ->get()
            ->keyBy('id');

        foreach ($items as $line) {
            $variant = $variants->get((int) $line['variant_id']);
            $fulfillmentType = $variant?->fulfillment_type ?? ($line['fulfillment_type'] ?? ProductVariant::FULFILLMENT_STOCKED);
            $isDropshipping = $fulfillmentType === ProductVariant::FULFILLMENT_DROPSHIPPING;
            $leadTimeDays = $variant?->supplier_lead_time_days ?? ($line['supplier_lead_time_days'] ?? null);

            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'variant_id' => (int) $line['variant_id'],
                'quantity' => (float) $line['quantity'],
                'price' => (float) $line['unit_price'],
                'fulfillment_type' => $isDropshipping ? ProductVariant::FULFILLMENT_DROPSHIPPING : ProductVariant::FULFILLMENT_STOCKED,
                'supplier_id' => $isDropshipping ? ($variant?->default_supplier_id ?? ($line['supplier_id'] ?? null)) : null,
                'supplier_cost' => $isDropshipping ? ($variant?->supplier_cost ?? ($line['supplier_cost'] ?? null)) : null,
                'dropship_status' => $isDropshipping ? 'pending_supplier_order' : null,
                'supplier_expected_delivery_at' => $isDropshipping && $leadTimeDays
                    ? now()->addDays((int) $leadTimeDays)
                    : null,
            ]);

            if ($isDropshipping) {
                $this->dropshippingService->createFulfillmentForOrderItem($orderItem);
                continue;
            }

            $this->inventoryService->stockOut([
                'variant_id' => (int) $line['variant_id'],
                'quantity' => (int) $line['quantity'],
                'employee_id' => auth()->id(),
                'reason' => $channel === 'pos'
                    ? 'Sale created from POS Order'
                    : 'Online order fulfillment',
                'source_type' => Order::class,
                'source_id' => $order->id,
                'note' => "Order Item #{$orderItem->id}",
            ]);
        }
    }

    private function handlePosSale(Order $order, ?int $userId, array $payload, string $channel): void
    {
        if ($channel !== 'pos') {
            return;
        }

        Sale::create([
            'employee_id' => auth()->id(),
            'customer_id' => $userId,
            'total_amount' => $order->total_amount,
            'order_id' => $order->id,
            'pos_terminal_id' => session()->get('pos_terminal_id'),
        ]);
    }

    private function handleShipping(Order $order, array $payload, object $session, ?int $userId): void
    {
        $shipping = $payload['shipping'] ?? (json_decode((string) ($session->shipping_snapshot ?? '{}'), true) ?: []);
        if (empty($shipping) || empty($shipping['shipping_method_id'])) {
            return;
        }

        $shipment = Shipment::create([
            'shippable_type' => Order::class,
            'shippable_id' => $order->id,
            'shipping_method_id' => $shipping['shipping_method_id'] ?? null,
            'type' => !empty($shipping['pickup_location_id']) ? 'pickup' : 'delivery',
            'weight' => $shipping['weight'] ?? 0,
            'cost' => (float) $session->shipping_total,
            'shipping_zone_id' => $shipping['shipping_zone_id'] ?? null,
            'currency' => $shipping['currency'] ?? 'NGN',
        ]);

        $line1 = $shipping['line1'] ?? $shipping['address'] ?? data_get($shipping, 'address.line1');
        $line2 = $shipping['line2'] ?? data_get($shipping, 'address.line2');
        $phone = $shipping['phone'] ?? data_get($shipping, 'address.phone');
        $email = $shipping['email'] ?? data_get($shipping, 'address.email');
        $stateId = $shipping['state_id'] ?? data_get($shipping, 'address.state_id');
        $lgaId = $shipping['lga_id'] ?? data_get($shipping, 'address.lga_id');
        $countryId = $shipping['country_id'] ?? data_get($shipping, 'address.country_id');
        $postalCode = $shipping['postal_code'] ?? data_get($shipping, 'address.postal_code');
        $recipientName = $shipping['recipient_name'] ?? data_get($shipping, 'address.name') ?? ($userId ? optional(User::find($userId))->name : 'Walk-In Customer');

        if (!empty($line1)) {
            Address::create([
                'shipment_id' => $shipment->id,
                'name' => $recipientName,
                'phone' => $phone,
                'email' => $email,
                'line1' => $line1,
                'line2' => $line2,
                'postal_code' => $postalCode,
                'country_id' => $countryId,
                'state_id' => $stateId,
                'lga_id' => $lgaId,
            ]);
        }

        if (!empty($shipping['pickup_location_id'])) {
            Pickup::query()->create([
                'shipment_id' => $shipment->id,
                'pickup_location_id' => (int) $shipping['pickup_location_id'],
                'contact_name' => $recipientName,
                'contact_phone' => $phone,
                'reference' => 'PU-' . strtoupper(Str::random(8)),
            ]);
        }

        if ((float) $session->shipping_total <= 0) {
            return;
        }

        $shipment->addPayment([
            'type' => 'inflow',
            'method' => $this->primaryPaymentMethod($payload),
            'amount' => (float) $session->shipping_total,
            'status' => $payload['shipping_payment_status'] ?? ($payload['payment_status'] ?? 'pending'),
            'transaction_reference' => $payload['transaction_reference'] ?? null,
            'note' => 'Order Shipment Charges',
        ]);
    }

    private function recordPayment(Order $order, array $payload, float $amount, string $channel): void
    {
        if ($channel === 'pos') {
            $paymentLines = $this->normalizePaymentLines($payload, $amount);

            foreach ($paymentLines as $index => $line) {
                $order->addPayment([
                    'type' => 'inflow',
                    'method' => $line['method'],
                    'amount' => $line['amount'],
                    'status' => $line['status'] ?? 'paid',
                    'paid_at' => $line['paid_at'] ?? now(),
                    'employee_id' => auth()->id(),
                    'transaction_reference' => $line['transaction_reference'] ?? null,
                    'meta' => [
                        'source' => 'pos_checkout',
                        'line_number' => $index + 1,
                    ],
                    'note' => 'POS Order Payment',
                ]);
            }

            return;
        }

        $order->addPayment([
            'type' => 'inflow',
            'method' => $payload['payment_method'] ?? 'cash',
            'amount' => $amount,
            'status' => $payload['payment_status'] ?? 'paid',
            'transaction_reference' => $payload['transaction_reference'] ?? null,
            'note' => $channel === 'pos' ? 'POS Order Payment' : 'Online Order Payment',
        ]);
    }

    private function syncReceivable(Order $order, array $payload, string $channel): void
    {
        if ($channel !== 'pos') {
            return;
        }

        $order->loadMissing('payments', 'sale', 'user');
        $outstanding = round((float) $order->outstandingBalance(), 2);

        if ($outstanding <= $this->moneyTolerance) {
            return;
        }

        if (!$order->user || strtolower((string) $order->user->email) === 'walkincustomer@example.com') {
            throw ValidationException::withMessages([
                'customer_id' => 'A saved customer is required for partial payment and receivable tracking.',
            ]);
        }

        $this->customerInvoiceService->createFromOrder($order, [
            'due_date' => $payload['due_date'] ?? null,
            'repayment_terms' => $payload['repayment_terms'] ?? null,
            'payment_breakdown' => $this->normalizePaymentLines($payload, (float) $order->total_amount),
        ], auth()->id());
    }

    private function finalizeOrderStatus(Order $order, string $channel): void
    {
        if ($channel === 'pos') {
            $order->status = 'completed';
        } elseif ($channel === 'online') {
            $order->status = 'paid';
        }

        $order->save();
    }

    private function commitDiscount(Order $order, $session): void
    {
        $snapshot = json_decode((string) $session->discount_snapshot, true) ?? [
            'discount_id' => $session->discount_id,
            'amount' => $session->discount_amount,
        ];

        if (!empty($snapshot['discount_id'])) {
            $this->discountService->commitFromSnapshot($order, $snapshot);
        }
    }

    private function markSessionUsed($session, Order $order): void
    {
        DB::table('checkout_sessions')->where('id', $session->id)->update([
            'used' => true,
            'order_id' => $order->id,
            'processed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function logOrder(Order $order): void
    {
        Log::channel('orders')->info('Order placed', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'channel' => $order->channel,
            'employee_id' => auth()->id(),
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function resolveUserId(array $payload, string $channel): ?int
    {
        $userId = $payload['customer_id'] ?? null;

        if ($channel === 'pos' && empty($userId)) {
            $walkInEmail = 'walkInCustomer@example.com';
            $walkInUser = User::where('email', $walkInEmail)->first();
            if (!$walkInUser) {
                throw new InvalidArgumentException("Walk-in customer user with email {$walkInEmail} not found. Please create that user or provide user_id.");
            }
            $userId = $walkInUser->id;
        }

        return $userId ? (int) $userId : null;
    }

    /**
     * @throws InvalidArgumentException|InsufficientStockException
     */
    protected function lockAndValidateStock(array $items): void
    {
        $variantIds = collect($items)->pluck('variant_id')->unique()->values()->all();

        $variants = ProductVariant::whereIn('id', $variantIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $insufficient = [];
        foreach ($items as $line) {
            $variantId = (int) $line['variant_id'];
            $quantity = (float) $line['quantity'];

            if ($quantity <= 0) {
                throw new InvalidArgumentException("Invalid quantity for variant {$variantId}");
            }

            $variant = $variants->get($variantId);
            if (!$variant) {
                throw new InvalidArgumentException("ProductVariant {$variantId} not found");
            }

            $available = $this->computeAvailableQuantity($variant);
            if ($variant->requiresLocalStock() && $available < $quantity) {
                $insufficient[] = [
                    'variant_id' => $variantId,
                    'sku' => $variant->sku ?? null,
                    'requested' => $quantity,
                    'available' => $available,
                ];
            }
        }

        if (!empty($insufficient)) {
            throw new InsufficientStockException('One or more items do not have enough stock', $insufficient);
        }
    }

    protected function generateOrderNumber(string $channel): string
    {
        $prefix = $channel === 'pos' ? 'POS' : 'WEB';
        $date = now()->format('Ymd');

        $token = strtoupper(Str::random(28));
        $candidate = "{$prefix}-{$date}-{$token}";

        while (Order::where('order_number', $candidate)->exists()) {
            $token = strtoupper(Str::random(28));
            $candidate = "{$prefix}-{$date}-{$token}";
        }

        return $candidate;
    }

    protected function computeAvailableQuantity(ProductVariant $variant): float
    {
        if (isset($variant->quantity) && isset($variant->reserved)) {
            return (float) ($variant->quantity - $variant->reserved);
        }

        if (isset($variant->quantity)) {
            return (float) $variant->quantity;
        }

        return 0.0;
    }

    private function normalizePaymentLines(array $payload, float $orderTotal): array
    {
        $lines = collect($payload['payment_lines'] ?? [])
            ->filter(fn ($line) => is_array($line))
            ->map(fn (array $line) => [
                'method' => (string) ($line['method'] ?? ''),
                'amount' => round((float) ($line['amount'] ?? 0), 2),
                'status' => (string) ($line['status'] ?? 'paid'),
                'paid_at' => $line['paid_at'] ?? now(),
                'transaction_reference' => $line['transaction_reference'] ?? null,
            ])
            ->filter(fn (array $line) => $line['method'] !== '' && $line['amount'] > 0)
            ->values();

        if ($lines->isEmpty() && !empty($payload['payment_method'])) {
            $lines = collect([[
                'method' => (string) $payload['payment_method'],
                'amount' => round($orderTotal, 2),
                'status' => (string) ($payload['payment_status'] ?? 'paid'),
                'paid_at' => now(),
                'transaction_reference' => $payload['transaction_reference'] ?? null,
            ]]);
        }

        $totalPaid = round((float) $lines->sum('amount'), 2);
        if ($totalPaid > round($orderTotal, 2) + $this->moneyTolerance) {
            throw ValidationException::withMessages([
                'payment_lines' => 'Total payment cannot exceed the order total.',
            ]);
        }

        return $lines->all();
    }

    private function primaryPaymentMethod(array $payload): string
    {
        if (!empty($payload['payment_lines'][0]['method'])) {
            return (string) $payload['payment_lines'][0]['method'];
        }

        return (string) ($payload['payment_method'] ?? 'cash');
    }
}


