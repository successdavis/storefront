<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Shipping\ShippingCostService;
use Illuminate\Validation\ValidationException;

class PricingQuoteService
{
    public function __construct(
        protected ProductService $productService,
        protected ShippingCostService $shippingCostService,
        protected DiscountService $discountService,
    ) {}

    /**
     * @param array{
     *  items: array<int, array{variant_id:int, quantity:int|float}>,
     *  user?: User|null,
     *  channel?: string,
     *  coupon?: string|null,
     *  shipping?: array|null,
     *  tax_total?: float|int|null,
     * } $payload
     * @return array{
     *  items: array<int, array{
     *      variant_id:int,
     *      quantity:float,
     *      unit_price:float,
     *      line_total:float,
     *      product_id:int|null,
     *      category_ids:array<int>,
     *      fulfillment_type:string,
     *      supplier_id:int|null,
     *      supplier_cost:float|null,
     *      supplier_lead_time_days:int|null
     *  }>,
     *  summary: array{
     *      item_count:int,
     *      subtotal:float,
     *      shipping_total:float,
     *      discount_amount:float,
     *      tax_total:float,
     *      total:float,
     *      shipping_free:bool,
     *      discount_id:int|null,
     *      discount_label:string|null,
     *      coupon:string|null
     *  },
     *  discount_snapshot: array{
     *      discount_id:int|null,
     *      code:string|null,
     *      label:string|null,
     *      amount:float
     *  },
     *  shipping_snapshot: array,
     * }
     */
    public function quote(array $payload): array
    {
        $payloadItems = $payload['items'] ?? [];
        if (empty($payloadItems)) {
            throw ValidationException::withMessages([
                'items' => 'At least one item is required to calculate pricing.',
            ]);
        }

        $user = $payload['user'] ?? null;
        if (!$user instanceof User && $user !== null) {
            throw new \InvalidArgumentException('user must be an instance of User or null');
        }

        $items = $this->resolveItems($payloadItems, $user);
        $subtotal = round((float) collect($items)->sum('line_total'), 2);

        $shippingSnapshot = $this->normalizeShippingSnapshot($payload['shipping'] ?? null);
        $shippingResult = $this->calculateShipping($shippingSnapshot, $items, $subtotal);
        $shippingTotal = round((float) ($shippingResult['total'] ?? 0.0), 2);
        $shippingFree = (bool) ($shippingResult['free_shipping_applied'] ?? false);

        $taxTotal = round((float) ($payload['tax_total'] ?? 0.0), 2);
        $channel = strtolower((string) ($payload['channel'] ?? 'online'));
        $coupon = $this->normalizeCoupon($payload['coupon'] ?? null);
        $discountQuote = $this->discountService->previewQuote(
            user: $user,
            items: $items,
            subtotal: $subtotal,
            shippingTotal: $shippingTotal,
            taxTotal: $taxTotal,
            channel: $channel,
            couponCode: $coupon,
        );

        $discountAmount = round((float) ($discountQuote['amount'] ?? 0.0), 2);
        $total = max(round($subtotal + $shippingTotal + $taxTotal - $discountAmount, 2), 0.0);

        return [
            'items' => $items,
            'summary' => [
                'item_count' => (int) collect($items)->sum('quantity'),
                'subtotal' => $subtotal,
                'shipping_total' => $shippingTotal,
                'discount_amount' => $discountAmount,
                'tax_total' => $taxTotal,
                'total' => $total,
                'shipping_free' => $shippingFree,
                'discount_id' => $discountQuote['discount_id'] ?? null,
                'discount_label' => $discountQuote['label'] ?? null,
                'coupon' => $coupon,
            ],
            'discount_snapshot' => [
                'discount_id' => $discountQuote['discount_id'] ?? null,
                'code' => $discountQuote['code'] ?? $coupon,
                'label' => $discountQuote['label'] ?? null,
                'amount' => $discountAmount,
            ],
            'shipping_snapshot' => $shippingSnapshot,
        ];
    }

    protected function resolveItems(array $payloadItems, ?User $user = null): array
    {
        $variantIds = collect($payloadItems)
            ->pluck('variant_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $variants = ProductVariant::query()
            ->active()
            ->with('product.categories:id')
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $items = [];
        foreach ($payloadItems as $line) {
            $variantId = (int) ($line['variant_id'] ?? 0);
            $quantity = (float) ($line['quantity'] ?? 0);

            if ($variantId <= 0 || $quantity <= 0) {
                continue;
            }

            $variant = $variants->get($variantId);
            if (!$variant) {
                throw ValidationException::withMessages([
                    'items' => "Variant {$variantId} could not be found.",
                ]);
            }

            $pricing = $this->productService->resolveVariantPricing($variant, $user, $variant->product);
            $unitPrice = round((float) ($pricing['current'] ?? 0), 2);
            $lineTotal = round($unitPrice * $quantity, 2);

            $items[] = [
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'product_id' => $variant->product_id ? (int) $variant->product_id : null,
                'category_ids' => $variant->product?->categories?->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [],
                'fulfillment_type' => $variant->fulfillment_type ?? ProductVariant::FULFILLMENT_STOCKED,
                'supplier_id' => $variant->default_supplier_id ? (int) $variant->default_supplier_id : null,
                'supplier_cost' => $variant->supplier_cost !== null ? (float) $variant->supplier_cost : null,
                'supplier_lead_time_days' => $variant->supplier_lead_time_days !== null ? (int) $variant->supplier_lead_time_days : null,
            ];
        }

        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'No valid items were provided for pricing.',
            ]);
        }

        return $items;
    }

    protected function calculateShipping(array $shippingSnapshot, array $items, float $subtotal): array
    {
        if (empty($shippingSnapshot['shipping_method_id'])) {
            return ['total' => 0.0, 'free_shipping_applied' => false];
        }

        return $this->shippingCostService->calculate([
            'shipping_method_id' => $shippingSnapshot['shipping_method_id'],
            'shipping_zone_id' => $shippingSnapshot['shipping_zone_id'],
            'pickup_location_id' => $shippingSnapshot['pickup_location_id'],
            'state_id' => $shippingSnapshot['state_id'],
            'lga_id' => $shippingSnapshot['lga_id'],
            'subtotal' => $subtotal,
            'items' => collect($items)->map(fn (array $item) => [
                'variant_id' => (int) $item['variant_id'],
                'quantity' => (float) $item['quantity'],
            ])->values()->all(),
        ]);
    }

    protected function normalizeShippingSnapshot(mixed $shipping): array
    {
        if (!is_array($shipping)) {
            return [];
        }

        return [
            'shipping_method_id' => !empty($shipping['shipping_method_id']) ? (int) $shipping['shipping_method_id'] : null,
            'shipping_zone_id' => !empty($shipping['shipping_zone_id']) ? (int) $shipping['shipping_zone_id'] : null,
            'pickup_location_id' => !empty($shipping['pickup_location_id']) ? (int) $shipping['pickup_location_id'] : null,
            'state_id' => !empty($shipping['state_id']) ? (int) $shipping['state_id'] : null,
            'lga_id' => !empty($shipping['lga_id']) ? (int) $shipping['lga_id'] : null,
            'country_id' => !empty($shipping['country_id']) ? (int) $shipping['country_id'] : null,
            'phone' => $this->normalizeNullableString($shipping['phone'] ?? null),
            'line1' => $this->normalizeNullableString($shipping['line1'] ?? ($shipping['address'] ?? null)),
            'address' => $this->normalizeNullableString($shipping['address'] ?? ($shipping['line1'] ?? null)),
        ];
    }

    protected function normalizeCoupon(mixed $coupon): ?string
    {
        if ($coupon === null) {
            return null;
        }

        $coupon = strtoupper(trim((string) $coupon));

        return $coupon !== '' ? $coupon : null;
    }

    protected function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
