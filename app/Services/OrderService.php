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
        if (empty($payload['items']) || !is_array($payload['items'])) {
            throw new InvalidArgumentException('items must be a non-empty array');
        }

        if (empty($payload['checkout_token']) || !is_string($payload['checkout_token'])) {
            throw ValidationException::withMessages(['checkout_token' => 'Missing checkout token.']);
        }

        $channel = isset($payload['channel']) && strtolower($payload['channel']) === 'pos'
            ? 'pos'
            : 'online';

        // top-level transaction for order creation + marking session used + discount commit
        return DB::transaction(function () use ($payload, $channel) {
            // 1) Load checkout session snapshot
            $token = $payload['checkout_token'];

            $session = DB::table('checkout_sessions')->where('token', $token)->lockForUpdate()->first();
            if (!$session) {
                throw ValidationException::withMessages(['checkout_token' => 'Invalid or expired checkout token.']);
            }

            if ($session->used) {
                throw ValidationException::withMessages(['checkout_token' => 'This checkout session has already been used.']);
            }

            if ($session->expires_at && \Carbon\Carbon::parse($session->expires_at)->isPast()) {
                throw ValidationException::withMessages(['checkout_token' => 'Checkout session expired. Please refresh checkout.']);
            }

            // 2) Resolve user & compute items/subtotal server-side as a safety check
            $userId = $this->resolveUserId($payload, $channel);
            $user = $userId ? User::find($userId) : null;

            // Validate items match the session snapshot (defensive)
            // Here we recompute items & subtotal using the payload items provided (you may prefer to use server product prices)
            $variants = $this->lockAndValidateStock($payload['items']);
            $items = $this->buildOrderItems($payload['items'], $variants);
            $computedSubtotal = $this->calculateSubtotalFromItems($items);

            // 3) Recompute shipping on server
            $computedShipping = 0.0;
            if (!empty($payload['shipping']) && is_array($payload['shipping'])) {
                $shippingParams = [
                    'shipping_method_id' => $payload['shipping']['shipping_method_id'] ?? null,
                    'shipping_zone_id'   => $payload['shipping']['shipping_zone_id'] ?? null,
                    'pickup_location_id' => $payload['shipping']['pickup_location_id'] ?? null,
                    'state_id'           => $payload['shipping']['state_id'] ?? null,
                    'subtotal'           => $computedSubtotal,
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

                $computedShipping = (float) $computed['total'];
            }

            // 4) Recompute discount on server (to detect any changes)
            $taxTotal = isset($payload['tax_total']) ? (float) $payload['tax_total'] : 0.0;

            $serverQuote = $this->discountService->previewQuote(
                $user,
                $items,
                $computedSubtotal,
                $computedShipping,
                $taxTotal,
                $channel,
                $payload['coupon'] ?? null
            );

            $serverDiscountAmount = round($serverQuote['amount'] ?? 0.0, 2);
            $serverTotals = $this->calculateTotals(
                subtotal: $computedSubtotal,
                taxTotal: $taxTotal,
                shippingTotal: $computedShipping,
                discount: $serverDiscountAmount
            );
            $serverTotalAmount = round($serverTotals['total_amount'], 2);

            // 5) Compare server recomputed values to the checkout session snapshot (authoritative)
            $sessionSubtotal = (float) $session->subtotal;
            $sessionShipping = (float) $session->shipping_total;
            $sessionDiscount = (float) $session->discount_amount;
            $sessionTotal = (float) $session->total;

            // Tolerance for floats
            $tol = $this->discountService->getMoneyTolerance();

            // If server recomputed differs from session snapshot -> reject (force a refresh)
            if (abs($sessionSubtotal - round($computedSubtotal,2)) > $tol
                || abs($sessionShipping - round($computedShipping,2)) > $tol
                || abs($sessionDiscount - $serverDiscountAmount) > $tol
                || abs($sessionTotal - $serverTotalAmount) > $tol
            ) {
                // Mismatch: cannot place order using stale snapshot
                throw ValidationException::withMessages([
                    'checkout' => 'Pricing changed since checkout preview. Please refresh checkout and pay again.'
                ]);
            }

            // 6) All good — create the Order using the SNAPSHOT values (what the user paid)
            $orderNumber = $this->generateOrderNumber($channel);

            $order = Order::create([
                'user_id'       => $userId,
                'subtotal'      => $sessionSubtotal,
                'tax_total'     => $taxTotal,
                'discount'      => $sessionDiscount,
                'currency'      => $payload['currency'] ?? 'NGN',
                'channel'       => $channel,
                'order_number'  => $orderNumber,
                'status'        => $payload['status'] ?? 'pending',
                'total_amount'  => $sessionTotal,
                'shipping_total'=> $sessionShipping,
            ]);

            // 7) Create order items + inventory adjustments (use computed $items)
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

            // 8) POS sale record
            if ($channel === 'pos') {
                Sale::create([
                    'employee_id'   => auth()->id(),
                    'customer_id'   => $userId,
                    'total_amount'  => $sessionTotal,
                    'payment_method'=> $payload['payment_method'] ?? 'cash',
                    'order_id'      => $order->id,
                    'pos_terminal_id' => session()->get('pos_terminal_id'),
                ]);
            }

            // 9) Shipping + address
            if (!empty($payload['shipping']) && is_array($payload['shipping'])) {
                $shipping = $payload['shipping'];
                $shipment = Shipment::create([
                    'shippable_type'   => Order::class,
                    'shippable_id'     => $order->id,
                    'shipping_method_id'=> $shipping['shipping_method_id'] ?? null,
                    'weight'           => $shipping['weight'] ?? 0,
                    'cost'             => $sessionShipping,
                ]);

                if (!empty($shipping['address']) && is_array($shipping['address'])) {
                    $addr = $shipping['address'];
                    Address::create([
                        'shipment_id' => $shipment->id,
                        'name'        => $userId ? optional(User::find($userId))->name : 'Walk-In Customer',
                        'phone'       => $addr['phone'] ?? null,
                        'line1'       => $addr['line1'] ?? null,
                        'country_id'  => $addr['country_id'] ?? null,
                        'state_id'    => $addr['state_id'] ?? null,
                        'lga_id'      => $addr['lga_id'] ?? null,
                    ]);
                }

                $shipment->addPayment([
                    'type'   => 'inflow',
                    'method' => $payload['payment_method'] ?? 'cash',
                    'amount' => $sessionShipping,
                    'status' => 'paid',
                    'note'   => 'Order Shipment Charges',
                ]);
            }

            // 10) Payments (the client should have already charged the user; just record it)
            $order->addPayment([
                'type'   => 'inflow',
                'method' => $payload['payment_method'] ?? 'cash',
                'amount' => $sessionTotal,
                'status' => $payload['payment_status'] ?? 'paid',
                'note'   => $channel === 'pos' ? 'POS Order Payment' : 'Online Order Payment',
            ]);

            if ($channel === 'pos') {
                $order->status = 'completed';
                $order->save();
            }

            // 11) Commit discount using the stored snapshot (no recompute)
            $discountSnapshot = json_decode($session->discount_snapshot, true) ?? [
                'discount_id' => $session->discount_id,
                'amount' => $session->discount_amount,
            ];

            if (!empty($discountSnapshot['discount_id'])) {
                $this->discountService->commitFromSnapshot($order, $discountSnapshot);
            }

            // 12) Mark checkout session used (prevent replay)
            DB::table('checkout_sessions')->where('id', $session->id)->update([
                'used' => true,
                'updated_at' => now(),
            ]);

            Log::channel('orders')->info('Order placed', [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'channel'      => $order->channel,
                'employee_id'  => auth()->id(),
            ]);

            return $order;
        }, 5); // outer transaction
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

