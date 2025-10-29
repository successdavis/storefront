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
        protected ShippingCostService $shippingService
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

        // normalize channel
        $channel = isset($payload['channel']) && strtolower($payload['channel']) === 'pos' ? 'pos' : 'online';

        return DB::transaction(function () use ($payload, $channel) {
            // 1) Resolve customer for order (user_id may be null for POS)
            $userId = $this->resolveUserId($payload, $channel);

            // 2) Lock and validate stock for each variant first (returns locked variants keyed by id)
            $variants = $this->lockAndValidateStock($payload['items']);

            // 3) Build items data and compute server-side subtotal
            $items = $this->buildOrderItems($payload['items'], $variants);
            $computedSubtotal = $this->calculateSubtotalFromItems($items);

            // 4) Compute shipping (server-side). Note: Shipping calculation should use computed subtotal.
            $computedShipping = 0.0;
            if (!empty($payload['shipping']) && is_array($payload['shipping'])) {
                $shippingParams = [
                    'shipping_method_id' => $payload['shipping']['shipping_method_id'] ?? null,
                    'shipping_zone_id' => $payload['shipping']['shipping_zone_id'] ?? null,
                    'pickup_location_id' => $payload['shipping']['pickup_location_id'] ?? null,
                    'subtotal' => $computedSubtotal,
                    'weight' => $payload['shipping']['weight'] ?? 0,
                    'items' => $payload['items'] ?? [],
                ];

                $computed = $this->shippingService->calculate($shippingParams);
                if (!isset($computed['total'])) {
                    Log::channel('orders')->error('ShippingService returned invalid data', ['params' => $shippingParams, 'result' => $computed]);
                    throw new \RuntimeException('Failed to compute shipping cost');
                }

                $computedShipping = (float) $computed['total'];
            }

            // 5) Tax and discount: prefer server values if you have server-side tax/discount rules.
            // For now, we use provided tax_total and discount (controller should validate these).
            $taxTotal = isset($payload['tax_total']) ? (float) $payload['tax_total'] : 0.0;
            $discount = isset($payload['discount']) ? (float) $payload['discount'] : 0.0;

            // 6) Calculate total amount server-side
            $computedTotals = $this->calculateTotals(
                subtotal: $computedSubtotal,
                taxTotal: $taxTotal,
                shippingTotal: $computedShipping,
                discount: $discount
            );

            // 7) If payload included subtotal / total, assert they match calculated values (strict)
            if (array_key_exists('subtotal', $payload)) {
                $this->assertAmountsMatch((float)$payload['subtotal'], $computedSubtotal, 'subtotal');
            }

            if (array_key_exists('total', $payload)) {
                $this->assertAmountsMatch((float)$payload['total'], $computedTotals['total_amount'], 'total');
            }

            // 8) Create Order using server-calculated amounts
            $orderNumber = $this->generateOrderNumber($channel);
            $order = Order::create([
                'user_id' => $userId,
                'subtotal' => $computedSubtotal,
                'tax_total' => $taxTotal,
                'discount' => $discount,
                'currency' => $payload['currency'] ?? 'NGN',
                'channel' => $channel === 'pos' ? 'pos' : 'online',
                'order_number' => $orderNumber,
                'status' => $payload['status'] ?? 'pending',
                'total_amount' => $computedTotals['total_amount'],
                'shipping_total' => $computedShipping,
            ]);

            // 9) Create Order Items and handle inventory adjustments (POS)
            foreach ($items as $line) {
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'variant_id' => $line['variant_id'],
                    'quantity' => $line['quantity'],
                    'price' => $line['unit_price'],
                    'line_total' => $line['line_total'] ?? ($line['unit_price'] * $line['quantity']),
                ]);

                if ($channel === 'pos') {
                    $this->inventoryService->stockOut([
                        'variant_id' => $line['variant_id'],
                        'quantity' => $line['quantity'],
                        'employee_id' => auth()->id(),
                        'reason' => 'Sale created from POS Order',
                        'source_type' => Order::class,
                        'source_id' => $order->id,
                        'note' => "Order Item #{$orderItem->id}",
                    ]);
                }
            }

            // 10) If POS channel: create Sale Record using server-calculated total
            $sale = null;
            if ($channel === 'pos') {
                $sale = Sale::create([
                    'employee_id' => auth()->id(),
                    'customer_id' => $userId,
                    'total_amount' => $computedTotals['total_amount'],
                    'payment_method' => $payload['payment_method'] ?? 'cash',
                    'order_id'       => $order->id,
                    // add other sale fields as needed
                ]);
            }

            // 11) If shipping present, persist shipment and address & register shipment payment
            if (!empty($payload['shipping']) && is_array($payload['shipping'])) {
                $shipping = $payload['shipping'];
                $shipment = Shipment::create([
                    'shippable_type' => Order::class,
                    'shippable_id' => $order->id,
                    'shipping_method_id' => $shipping['shipping_method_id'] ?? null,
                    'weight' => $shipping['weight'] ?? 0,
                    'cost' => $computedShipping,
                ]);

                if (!empty($shipping['address']) && is_array($shipping['address'])) {
                    $addr = $shipping['address'];
                    Address::create([
                        'shipment_id' => $shipment->id,
                        'name' => $userId ? optional(User::find($userId))->name : 'Walk-In Customer',
                        'phone' => $addr['phone'] ?? null,
                        'line1' => $addr['line1'] ?? null,
                        'country_id' => $addr['country_id'] ?? null,
                        'state_id' => $addr['state_id'] ?? null,
                        'lga_id' => $addr['lga_id'] ?? null,
                    ]);
                }

                // record shipment payment (treat shipping as paid from order payment)
                $shipment->addPayment([
                    'type' => 'inflow',
                    'method' => $payload['payment_method'] ?? 'cash',
                    'amount' => $computedShipping,
                    'status' => 'paid',
                    'note' => 'Order Shipment Charges',
                ]);
            }

            // 12) Register order payment (use server-calculated total_amount)
            $order->addPayment([
                'type' => 'inflow',
                'method' => $payload['payment_method'] ?? 'cash',
                'amount' => $computedTotals['total_amount'],
                'status' => $payload['payment_status'] ?? 'paid',
                'note' => $channel === 'pos' ? 'POS Order Payment' : 'Online Order Payment',
            ]);

            if ($channel === 'pos') {
                $order->status = 'completed';
                $order->save();
            }

            Log::channel('orders')->info('Order placed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'channel' => $order->channel,
                'employee_id' => auth()->id(),
            ]);

            return $order;
        }, 5); // retry attempts for deadlocks
    }

    /**
     * Resolve user id for order creation.
     *
     * @throws InvalidArgumentException
     */
    protected function resolveUserId(array $payload, string $channel): ?int
    {
        $userId = $payload['user_id'] ?? null;

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

            // Prefer authoritative server-side price from variant when available
            $unitPrice = null;
            if (isset($variant->price) && $variant->price !== null) {
                $unitPrice = (float) $variant->price;
            } elseif (isset($line['price'])) {
                // fallback to provided price (not recommended)
                $unitPrice = (float) $line['price'];
            } else {
                throw new InvalidArgumentException("Price not available for variant {$variantId}");
            }

            $lineTotal = round($unitPrice * $qty, 2);

            $items[] = [
                'variant_id' => $variantId,
                'quantity' => $qty,
                'unit_price' => round($unitPrice, 2),
                'line_total' => $lineTotal,
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
