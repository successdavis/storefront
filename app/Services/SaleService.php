<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Address;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Shipping\ShippingCostService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SaleService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected ShippingCostService $shippingService
    ) {}

    /**
     * Place a sale (POS). Expects validated payload from controller.
     *
     * $payload shape:
     * [
     *   'customer_id' => ?int,
     *   'items' => [
     *       ['variant_id' => int, 'quantity' => int|float, 'price' => float],
     *       ...
     *   ],
     *   'total' => float,
     *   'payment_method' => ?string,
     *   'shipping' => ?array (shipping_method_id, weight, shipping_zone_id, pickup_location_id, address => [...])
     * ]
     *
     * @throws InsufficientStockException
     * @throws \Throwable
     */
    public function handle(array $payload): Sale
    {
        // Defensive: minimal shape checks (controller should validate fully)
        if (empty($payload['items']) || !is_array($payload['items'])) {
            throw new \InvalidArgumentException('items must be a non-empty array');
        }

        // Use DB transaction and row-level locking to avoid race conditions
        return DB::transaction(function () use ($payload) {
            // 1) Lock and validate stock for each variant first (pessimistic lock)
            // Build a map of variant models (locked)
            $variantIds = collect($payload['items'])->pluck('variant_id')->unique()->values()->all();

            // fetch variants with FOR UPDATE
            $variants = ProductVariant::whereIn('id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // verify all variants were loaded
            foreach ($variantIds as $vid) {
                if (!isset($variants[$vid])) {
                    throw new \InvalidArgumentException("ProductVariant {$vid} not found");
                }
            }

            // check availability
            $insufficient = [];
            foreach ($payload['items'] as $line) {
                $vid = (int) $line['variant_id'];
                $qty = (float) $line['quantity'];

                $variant = $variants[$vid];

                // assume ProductVariant has 'available_quantity' attribute or calculate as needed
                $available = $this->computeAvailableQuantity($variant);

                if ($qty <= 0) {
                    throw new \InvalidArgumentException("Invalid quantity for variant {$vid}");
                }

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

            // 2) Create sale
            $sale = Sale::create([
                'employee_id' => auth()->id(),
                'customer_id' => $payload['customer_id'] ?? null,
                'total_amount' => $payload['total'] ?? 0,
                'payment_method' => $payload['payment_method'] ?? 'cash',
            ]);

            // 3) Create sale items and immediately stock out (no address checks)
            foreach ($payload['items'] as $line) {
                $variantId = (int) $line['variant_id'];
                $qty = (float) $line['quantity'];
                $price = (float) $line['price'];

                $variant = $variants[$variantId];

                // create sale item
                $saleItem = SaleItem::create([
                    'sale_id' => $sale->id,
                    'variant_id' => $variant->id,
                    'quantity' => $qty,
                    'price' => $price,
                ]);

                // Immediately stock out using InventoryService
                $this->inventoryService->stockOut([
                    'variant_id' => $variant->id,
                    'quantity' => $qty,
                    'employee_id' => auth()->id(),
                    'reason' => 'Sale to customer - POS',
                    'source_type' => Sale::class,
                    'source_id' => $sale->id,
                    'note' => "Sale Item #{$saleItem->id}",
                ]);
            }

            // 4) If shipping requested, calculate cost securely and create shipment
            if (!empty($payload['shipping']) && is_array($payload['shipping'])) {
                $shipping = $payload['shipping'];

                // Prepare parameters for shipping calc - do not trust client 'shipping_cost'
                $shippingParams = [
                    'shipping_method_id'    => $shipping['shipping_method_id'] ?? null,
                    'shipping_zone_id'      => $shipping['shipping_zone_id'] ?? null,
                    'pickup_location_id'    => $shipping['pickup_location_id'] ?? null,
                    'subtotal'              => $payload['total'] ?? 0,
                    'weight'                => $shipping['weight'] ?? 0,
                    'items'                 => $payload['items'] ?? [],
                ];

                // ShippingService should return array with 'cost' (float) at minimum
                $computed = $this->shippingService->calculate($shippingParams);


                if (!is_array($computed) || !isset($computed['total'])) {
                    // defensive: ensure we have a numeric cost
                    Log::channel('sales')->error('ShippingService returned invalid structure', ['payload' => $shippingParams, 'result' => $computed]);
                    throw new \RuntimeException('Failed to compute shipping cost');
                }

                $cost = (float) $computed['total'];

                // create shipment
                $shipment = Shipment::create([
                    'shippable_type' => Sale::class,
                    'shippable_id' => $sale->id,
                    'shipping_method_id' => $shipping['shipping_method_id'] ?? null,
                    'weight' => $shipping['weight'] ?? 0,
                    'cost' => $cost,
                ]);

                // create address if provided (address creation itself is optional)
                if (!empty($shipping['address']) && is_array($shipping['address'])) {
                    $addr = $shipping['address'];
                    Address::create([
                        'shipment_id' => $shipment->id,
                        'name' => $payload['customer_id'] ? optional(User::find($payload['customer_id']))->name : 'Walk-In Customer',
                        'phone' => $addr['phone'] ?? null,
                        'line1' => $addr['line1'] ?? null,
                        'country_id' => $addr['country_id'] ?? null,
                        'state_id' => $addr['state_id'] ?? null,
                        'lga_id' => $addr['lga_id'] ?? null,
                    ]);
                }

                // record shipment payment (we treat shipping cost as paid from sale payment)
                $shipment->addPayment([
                    'type' => 'inflow',
                    'method' => $payload['payment_method'] ?? 'cash',
                    'amount' => $cost,
                    'status' => 'paid',
                    'note' => 'Sale Shipment Charges',
                ]);
            }

            // 5) Register sale payment
            $sale->addPayment([
                'type' => 'inflow',
                'method' => $payload['payment_method'] ?? 'cash',
                'amount' => $payload['total'] ?? 0,
                'status' => 'paid',
                'note' => 'Sale to customer - POS',
            ]);

            // log success
            Log::channel('sales')->info('Sale placed', ['sale_id' => $sale->id, 'employee_id' => auth()->id()]);

            return $sale;
        }, 5); // 5 attempts for deadlock retries
    }

    /**
     * Determine the available quantity for a variant.
     * Adapt this to your schema (e.g. quantity - reserved - reserved_for_returns etc).
     */
    protected function computeAvailableQuantity(ProductVariant $variant): float
    {
        // Example: if you store `quantity` and `reserved`
        if (isset($variant->quantity) && isset($variant->reserved)) {
            return (float) ($variant->quantity - $variant->reserved);
        }

        // fallback: return `quantity` if present
        if (isset($variant->quantity)) {
            return (float) $variant->quantity;
        }

        // otherwise assume zero — force explicit schema
        return 0.0;
    }
}
