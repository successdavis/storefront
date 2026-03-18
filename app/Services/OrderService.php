<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Shipping\ShippingCostService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class OrderService
{
    /**
     * Tolerance when comparing money amounts (avoid float precision issues).
     * 0.01 = 1 cent / smallest currency unit in most currencies.
     */
    protected float $moneyTolerance = 0.01;

    public function __construct(
        protected InventoryService $inventoryService,
        protected ShippingCostService $shippingService,
        protected DiscountService $discountService,
    ) {}

    /**
     * Handle order creation + optional POS sale.
     *
     * Expected $payload shape (controller should validate more strictly):
     *
     * [
     *   'user_id' => ?int, // customer id (nullable for POS walk-in)
     *   'channel' => 'online'|'pos',
     *   'items' => [
     *       ['variant_id' => int, 'quantity' => int|float, 'price' => float], // price may be provided but server will prefer variant price
     *       ...
     *   ],
     *   'subtotal' => float, // optional (will be validated if provided)
     *   'shipping' => ?array,
     *   'payment_method' => ?string,
     *   'shipping_total' => float, // optional (server calculates shipping_total)
     *   'tax_total' => float,
     *   'discount' => float,
     *   'total' => float, // optional (will be validated if provided)
     * ]
     *
     * @throws InsufficientStockException
     * @throws \Throwable
     * @return Order
     */
    public function handle(array $payload): Order
    {
        $this->validatePayload($payload);

        $channel = $this->resolveChannel($payload);

        return DB::transaction(function () use ($payload, $channel) {
            $session = $this->getValidCheckoutSession($payload['checkout_token']);

            [$userId, $user] = $this->resolveUser($payload, $channel);

            [$items, $computedSubtotal] = $this->processItems($payload['items']);


            $computedShipping = $this->computeShipping($payload, $computedSubtotal);

            [$serverDiscountAmount, $serverTotalAmount] = $this->computeDiscountAndTotals(
                $user,
                $items,
                $computedSubtotal,
                $computedShipping,
                $payload,
                $channel
            );

            $this->assertSessionIntegrity(
                $session,
                $computedSubtotal,
                $computedShipping,
                $serverDiscountAmount,
                $serverTotalAmount,
                $channel
            );

            $order = $this->createOrderFromSession($session, $payload, $userId, $channel);

            $this->createOrderItemsAndAdjustInventory($order, $items, $channel);

            $this->handlePosSale($order, $userId, $payload, $channel);

            $this->handleShipping($order, $payload, $sessionShipping = (float) $session->shipping_total, $userId, $channel);

            $this->recordPayment($order, $payload, (float) $session->total, $channel);

            $this->finalizeOrderStatus($order, $channel);

            $this->commitDiscount($order, $session);

            $this->markSessionUsed($session);

            $this->logOrder($order);

            return $order;
        }, 5);
    }

    private function validatePayload(array $payload): void
    {
        if (empty($payload['items']) || !is_array($payload['items'])) {
            throw new InvalidArgumentException('items must be a non-empty array');
        }

        if (empty($payload['checkout_token']) || !is_string($payload['checkout_token'])) {
            throw ValidationException::withMessages([
                'checkout_token' => 'Missing checkout token.'
            ]);
        }
    }

    private function resolveChannel(array $payload): string
    {
        return isset($payload['channel']) && strtolower($payload['channel']) === 'pos'
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
                'checkout_token' => 'Invalid or expired checkout token.'
            ]);
        }

        if ($session->used) {
            throw ValidationException::withMessages([
                'checkout_token' => 'This checkout session has already been used.'
            ]);
        }

        if ($session->expires_at && \Carbon\Carbon::parse($session->expires_at)->isPast()) {
            throw ValidationException::withMessages([
                'checkout_token' => 'Checkout session expired. Please refresh checkout.'
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

    private function processItems(array $payloadItems): array
    {
        $variants = $this->lockAndValidateStock($payloadItems);
        $items = $this->buildOrderItems($payloadItems, $variants);
        $subtotal = $this->calculateSubtotalFromItems($items);

        return [$items, $subtotal];
    }

    private function computeShipping(array $payload, float $subtotal): float
    {
        if (empty($payload['shipping']) || !is_array($payload['shipping'])) {
            return 0.0;
        }

        $shippingParams = [
            'shipping_method_id' => $payload['shipping']['shipping_method_id'] ?? null,
            'shipping_zone_id'   => $payload['shipping']['shipping_zone_id'] ?? null,
            'pickup_location_id' => $payload['shipping']['pickup_location_id'] ?? null,
            'state_id'           => $payload['shipping']['state_id'] ?? null,
            'subtotal'           => $subtotal,
            'items'              => $payload['items'] ?? [],
        ];

        $computed = $this->shippingService->calculate($shippingParams);

        if (!isset($computed['total'])) {
            Log::channel('orders')->error('ShippingService returned invalid data', [
                'params' => $shippingParams,
                'result' => $computed
            ]);

            throw new \RuntimeException('Failed to compute shipping cost');
        }

        return (float) $computed['total'];
    }


    private function computeDiscountAndTotals(
        $user,
        array $items,
        float $subtotal,
        float $shipping,
        array $payload,
        string $channel
    ): array {
        $taxTotal = (float) ($payload['tax_total'] ?? 0);

        $quote = $this->discountService->previewQuote(
            $user,
            $items,
            $subtotal,
            $shipping,
            $taxTotal,
            $channel,
            $payload['coupon'] ?? null
        );

        $discount = round($quote['amount'] ?? 0, 2);

        $totals = $this->calculateTotals(
            subtotal: $subtotal,
            taxTotal: $taxTotal,
            shippingTotal: $shipping,
            discount: $discount
        );

        return [$discount, round($totals['total_amount'], 2)];
    }

    private function assertSessionIntegrity($session, $subtotal, $shipping, $discount, $total, $channel): void
    {
        $tol = $this->discountService->getMoneyTolerance();

        if (
            abs($session->subtotal - round($subtotal, 2)) > $tol ||
            abs($session->shipping_total - round($shipping, 2)) > $tol ||
            abs($session->discount_amount - $discount) > $tol ||
            abs($session->total - $total) > $tol
        ) {
            if ($channel === 'online') {
                // DO NOT FAIL
                Log::warning('Checkout session mismatch after payment — honoring session snapshot', [
                    'session_id' => $session->id,
                    'expected' => [
                        'subtotal' => $session->subtotal,
                        'shipping' => $session->shipping_total,
                        'discount' => $session->discount_amount,
                        'total'    => $session->total,
                    ],
                    'computed' => [
                        'subtotal' => $subtotal,
                        'shipping' => $shipping,
                        'discount' => $discount,
                        'total'    => $total,
                    ],
                ]);
            } else {
                // POS → strict validation
                throw ValidationException::withMessages([
                    'checkout' => 'Pricing changed since checkout preview. Please refresh checkout and pay again.'
                ]);
            }
        }
    }

    private function createOrderFromSession($session, array $payload, $userId, string $channel): Order
    {
        return Order::create([
            'user_id'       => $userId,
            'subtotal'      => (float) $session->subtotal,
            'tax_total'     => (float) ($payload['tax_total'] ?? 0),
            'discount'      => (float) $session->discount_amount,
            'currency'      => $payload['currency'] ?? 'NGN',
            'channel'       => $channel,
            'order_number'  => $this->generateOrderNumber($channel),
            'status'        => $payload['status'] ?? 'pending',
            'total_amount'  => (float) $session->total,
            'shipping_total'=> (float) $session->shipping_total,
        ]);
    }

    private function createOrderItemsAndAdjustInventory(Order $order, array $items, string $channel): void
    {
        foreach ($items as $line) {
            $orderItem = OrderItem::create([
                'order_id'   => $order->id,
                'variant_id' => $line['variant_id'],
                'quantity'   => $line['quantity'],
                'price'      => $line['unit_price'],
                'line_total' => $line['line_total'],
            ]);

            if ($channel === 'pos') {
                $this->inventoryService->stockOut([
                    'variant_id'  => $line['variant_id'],
                    'quantity'    => $line['quantity'],
                    'employee_id' => auth()->id(),
                    'reason'      => 'Sale created from POS Order',
                    'source_type' => Order::class,
                    'source_id'   => $order->id,
                    'note'        => "Order Item #{$orderItem->id}",
                ]);
            }
        }
    }

    private function handlePosSale(Order $order, $userId, array $payload, string $channel): void
    {
        if ($channel !== 'pos') return;

        Sale::create([
            'employee_id'   => auth()->id(),
            'customer_id'   => $userId,
            'total_amount'  => $order->total_amount,
            'payment_method'=> $payload['payment_method'] ?? 'cash',
            'order_id'      => $order->id,
            'pos_terminal_id' => session()->get('pos_terminal_id'),
        ]);
    }

    private function handleShipping(Order $order, array $payload, float $shippingTotal, $userId): void
    {
        if (empty($payload['shipping'])) return;

        $shipping = $payload['shipping'];

        $shipment = Shipment::create([
            'shippable_type'   => Order::class,
            'shippable_id'     => $order->id,
            'shipping_method_id'=> $shipping['shipping_method_id'] ?? null,
            'weight'           => $shipping['weight'] ?? 0,
            'cost'             => $shippingTotal,
        ]);


        if (!empty($shipping['address'])) {
            Address::create([
                'shipment_id' => $shipment->id,
                'name'        => $userId ? optional(User::find($userId))->name : 'Walk-In Customer',
                'phone'       => $shipping['phone'] ?? null,
                'line1'       => $shipping['address']?? null,
                'country_id'  => $shipping['country_id'] ?? null,
                'state_id'    => $shipping['state_id'] ?? null,
                'lga_id'      => $shipping['lga_id'] ?? null,
            ]);
        }

        $shipment->addPayment([
            'type'   => 'inflow',
            'method' => $payload['payment_method'] ?? 'cash',
            'amount' => $shippingTotal,
            'status' => $payload['shipping_payment_status']
                ?? ($payload['payment_status'] ?? 'pending'),
            'transaction_reference' => $payload['transaction_reference'] ?? null,
            'note' => 'Order Shipment Charges',
        ]);
    }

    private function recordPayment(Order $order, array $payload, float $amount, string $channel): void
    {
        $order->addPayment([
            'type'   => 'inflow',
            'method' => $payload['payment_method'] ?? 'cash',
            'amount' => $amount,
            'status' => $payload['payment_status'] ?? 'paid',
            'transaction_reference' => $payload['transaction_reference'] ?? null,
            'note'   => $channel === 'pos'
                ? 'POS Order Payment'
                : 'Online Order Payment',
        ]);
    }

    private function finalizeOrderStatus(Order $order, string $channel): void
    {
        if ($channel === 'pos') {
            $order->status = 'completed';
            $order->save();
        }
    }

    private function commitDiscount(Order $order, $session): void
    {
        $snapshot = json_decode($session->discount_snapshot, true) ?? [
            'discount_id' => $session->discount_id,
            'amount' => $session->discount_amount,
        ];

        if (!empty($snapshot['discount_id'])) {
            $this->discountService->commitFromSnapshot($order, $snapshot);
        }
    }

    private function markSessionUsed($session): void
    {
        DB::table('checkout_sessions')->where('id', $session->id)->update([
            'used' => true,
            'updated_at' => now(),
        ]);
    }

    private function logOrder(Order $order): void
    {
        Log::channel('orders')->info('Order placed', [
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
            'channel'      => $order->channel,
            'employee_id'  => auth()->id(),
        ]);
    }
    /**
     * Resolve user id for order creation.
     *
     * @throws InvalidArgumentException
     */
    protected function resolveUserId(array $payload, string $channel): ?int
    {
        $userId = $payload['customer_id'] ?? null;

        if ($channel === 'pos' && empty($userId)) {
            $walkInEmail = 'walkInCustomer@example.com';
            $walkInUser = User::where('email', $walkInEmail)->first();
            if (!$walkInUser) {
                // Intentionally fail to prompt admin to create walk-in user as requested.
                throw new InvalidArgumentException("Walk-in customer user with email {$walkInEmail} not found. Please create that user or provide user_id.");
            }
            $userId = $walkInUser->id;
        }

        return $userId;
    }

    /**
     * Lock variants and validate stock availability. Returns collection keyed by id.
     *
     * @throws InvalidArgumentException|InsufficientStockException
     */
    protected function lockAndValidateStock(array $payloadItems)
    {
        $variantIds = collect($payloadItems)->pluck('variant_id')->unique()->values()->all();

        $variants = ProductVariant::whereIn('id', $variantIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($variantIds as $vid) {
            if (!isset($variants[$vid])) {
                throw new InvalidArgumentException("ProductVariant {$vid} not found");
            }
        }

        $insufficient = [];
        foreach ($payloadItems as $line) {
            $vid = (int) $line['variant_id'];
            $qty = (float) $line['quantity'];

            if ($qty <= 0) {
                throw new InvalidArgumentException("Invalid quantity for variant {$vid}");
            }

            $variant = $variants[$vid];
            $available = $this->computeAvailableQuantity($variant);

            if ($available < $qty) {
                $insufficient[] = [
                    'variant_id' => $vid,
                    'sku' => $variant->sku ?? null,
                    'requested' => $qty,
                    'available' => $available,
                ];
            }
        }

        if (!empty($insufficient)) {
            throw new InsufficientStockException('One or more items do not have enough stock', $insufficient);
        }

        return $variants;
    }

    /**
     * Build normalized order items array from payload and locked variants.
     * This will prefer variant price when available.
     *
     * returns array of:
     * [
     *   ['variant_id'=>int, 'quantity'=>float, 'unit_price'=>float, 'line_total'=>float],
     *   ...
     * ]
     */
    protected function buildOrderItems(array $payloadItems, $variants): array
    {
        $items = [];
        foreach ($payloadItems as $line) {
            $variantId = (int) $line['variant_id'];
            $qty = (float) $line['quantity'];

            $variant = $variants[$variantId];

            // Prefer authoritative server-side variant price (sale-aware), then fallback to payload price.
            $serverPrice = null;
            $saleWindowOpen = (!$variant->sale_starts_at || $variant->sale_starts_at->isPast())
                && (!$variant->sale_ends_at || $variant->sale_ends_at->isFuture());

            if ($variant->sale_price !== null
                && (float) $variant->sale_price > 0
                && (float) $variant->sale_price < (float) $variant->regular_price
                && $saleWindowOpen
            ) {
                $serverPrice = (float) $variant->sale_price;
            } elseif ($variant->regular_price !== null) {
                $serverPrice = (float) $variant->regular_price;
            }

            if ($serverPrice !== null) {
                $unitPrice = $serverPrice;
            } elseif (isset($line['price'])) {
                $unitPrice = (float) $line['price'];
            } else {
                throw new InvalidArgumentException("Price not available for variant {$variantId}");
            }

            $lineTotal = round($unitPrice * $qty, 2);

            // 👇 Enrich with product + categories
            $product    = $variant->product;
            $categories = $product && $product->categories
                ? $product->categories->pluck('id')->all()
                : [];

            $items[] = [
                'variant_id'   => $variantId,
                'quantity'     => $qty,
                'unit_price'   => round($unitPrice, 2),
                'line_total'   => $lineTotal,
                'product_id'   => $product?->id,
                'category_ids' => $categories,
            ];
        }

        return $items;
    }

    /**
     * Calculate subtotal from normalized items.
     */
    protected function calculateSubtotalFromItems(array $items): float
    {
        $subtotal = 0.0;
        foreach ($items as $line) {
            $subtotal += (float) $line['line_total'];
        }
        return round($subtotal, 2);
    }

    /**
     * Calculate totals given components. Returns array with keys:
     *  - subtotal
     *  - tax_total
     *  - shipping_total
     *  - discount
     *  - total_amount
     */
    protected function calculateTotals(float $subtotal, float $taxTotal, float $shippingTotal, float $discount): array
    {
        $subtotal = round($subtotal, 2);
        $taxTotal = round($taxTotal, 2);
        $shippingTotal = round($shippingTotal, 2);
        $discount = round($discount, 2);

        $total = $subtotal + $taxTotal + $shippingTotal - $discount;
        $total = round($total, 2);

        return [
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'shipping_total' => $shippingTotal,
            'discount' => $discount,
            'total_amount' => $total,
        ];
    }

    /**
     * Assert that provided amount matches expected amount within tolerance.
     *
     * @throws InvalidArgumentException
     */
    protected function assertAmountsMatch(float $provided, float $expected, string $fieldName): void
    {
        $provided = round($provided, 2);
        $expected = round($expected, 2);

        if (abs($provided - $expected) > $this->moneyTolerance) {
            throw new InvalidArgumentException(sprintf(
                "Provided %s (%s) does not match server-calculated %s (%s). Operation aborted.",
                $fieldName,
                number_format($provided, 2, '.', ''),
                $fieldName,
                number_format($expected, 2, '.', '')
            ));
        }
    }

    /**
     * Generate order number per channel rules.
     * Examples:
     *  - online: WEB-20251023-01K87AJR5HT59XWPV9NRMDCD8D
     *  - pos:   POS-20251023-01K87AJR5Q17FPQHQVGGE2NXN7
     */
    protected function generateOrderNumber(string $channel): string
    {
        $prefix = $channel === 'pos' ? 'POS' : 'WEB';
        $date = now()->format('Ymd');

        // Try to create a compact unique token. Adjust length to taste.
        $token = strtoupper(Str::random(28));

        $candidate = "{$prefix}-{$date}-{$token}";

        // Ensure uniqueness (rare collision) — loop until unique (defensive)
        while (Order::where('order_number', $candidate)->exists()) {
            $token = strtoupper(Str::random(28));
            $candidate = "{$prefix}-{$date}-{$token}";
        }

        return $candidate;
    }

    /**
     * Compute available quantity for a variant.
     * Adapt to your schema (quantity - reserved - reserved_for_returns etc).
     */
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
}




