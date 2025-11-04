<?php

namespace App\Services\Shipping;

use App\Exceptions\ShippingRateNotFoundException;
use App\Models\ShippingRate;
use App\Models\ProductVariant;
use App\Models\State;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class ShippingCostService
{
    public function calculate(array $payload): array
    {
        $now = Carbon::now();

        if (!isset($payload['shipping_method_id'])) {
            throw new \InvalidArgumentException('shipping_method_id is required');
        }

        $shippingMethodId = (int) $payload['shipping_method_id'];
        $subtotal = isset($payload['subtotal']) ? (float) $payload['subtotal'] : 0.0;
        $weightKg = isset($payload['weight_kg']) ? (float) $payload['weight_kg'] : 0.0;
        $shippingZoneId = $payload['shipping_zone_id'] ?? null;
        $stateId = $payload['state_id'] ?? null;
        $volumetricDivisor = $payload['volumetric_divisor'] ?? 5000.0;
        $useVolumetric = ($payload['use_volumetric'] ?? true);

        if (is_null($shippingZoneId) && !is_null($stateId)) {
            $shippingZoneId = $this->resolveZoneForState((int)$stateId);
        }

        // 1) compute volumetric weight if items provided and volumetric enabled
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        if ($useVolumetric && !empty($items)) {
            $volumetricWeight = $this->computeVolumetricWeight($items, (float)$volumetricDivisor);
            // use greater of actual weight and volumetric
            $weightKg = max($weightKg, $volumetricWeight);
            Log::channel('shippingcost')->debug('Volumetric weight computed', ['volumetricKg' => $volumetricWeight, 'weightKg' => $weightKg]);
        }

        // 2) If weight still zero or not provided => compute weight from items/variants
        if ($weightKg <= 0.0 && !empty($items)) {
            $computedWeight = $this->computeWeightFromItems($items);
            if ($computedWeight > 0.0) {
                $weightKg = $computedWeight;
                Log::channel('shippingcost')->debug('Computed weight from items/variants', ['computedKg' => $computedWeight]);
            } else {
                Log::channel('shippingcost')->debug('Computed weight from items returned 0. Items payload', ['items' => $items]);
            }
        }

        // 3) find applicable rates
        $rates = $this->getApplicableRates($shippingMethodId, $shippingZoneId, $now, $subtotal, $weightKg);
        if ($rates->isEmpty()) {
            $rates = $this->getApplicableRates($shippingMethodId, null, $now, $subtotal, $weightKg);
        }
        if ($rates->isEmpty()) {
            throw new ShippingRateNotFoundException('No shipping rate found for the provided criteria.');
        }

        // 4) select rate and compute cost
        $rate = $this->selectBestRate($rates, $weightKg, $subtotal);

        // guard: if rate depends on weight but weight is still zero -> fail with clear message
        if (in_array($rate->rate_type, ['per_kg', 'hybrid']) && $weightKg <= 0) {
            // Defensive — prefer throwing error than silently returning base rate only
            throw new \InvalidArgumentException("Weight must be provided or derivable from items for rate_type '{$rate->rate_type}'.");
        }

        Log::channel('shippingcost')->debug('ShippingRate used', [
            'id' => $rate->id,
            'rate_type' => $rate->rate_type,
            'base_rate' => (string)$rate->base_rate,
            'per_kg' => (string)$rate->per_kg,
            'weightKg' => $weightKg,
            'zone_id' => $shippingZoneId,
        ]);

        $calculation = $this->calculateByRateType($rate, $weightKg, $subtotal);

        $surcharge = (float) $rate->surcharge;
        $calculation['surcharge'] = round($surcharge, 2);
        $calculation['total_before_free_check'] = round($calculation['total'] + $surcharge, 2);

        if (!is_null($rate->free_shipping_threshold) && $subtotal >= (float) $rate->free_shipping_threshold) {
            $calculation['total'] = 0.00;
            $calculation['free_shipping_applied'] = true;
        } else {
            $calculation['total'] = round($calculation['total'] + $surcharge, 2);
            $calculation['free_shipping_applied'] = false;
        }

        $calculation['currency'] = $rate->currency ?? 'NGN';
        $calculation['rate_id'] = $rate->id;
        $calculation['shipping_method_id'] = $shippingMethodId;
        $calculation['shipping_zone_id'] = $shippingZoneId;
        $calculation['used_volumetric_kg'] = $useVolumetric ? round($weightKg, 3) : null;
        $calculation['rate_type'] = $rate->rate_type;

        return $calculation;
    }

    protected function computeVolumetricWeight(array $items, float $divisor = 5000.0): float
    {
        $totalVolKg = 0.0;
        foreach ($items as $item) {
            $qty = max(1, (int)($item['quantity'] ?? 1));
            $l = (float)($item['length_cm'] ?? 0.0);
            $w = (float)($item['width_cm'] ?? 0.0);
            $h = (float)($item['height_cm'] ?? 0.0);
            if ($l <= 0 || $w <= 0 || $h <= 0) {
                continue;
            }
            $vol = ($l * $w * $h) * $qty; // cm^3
            $kg = $vol / $divisor; // convert to kg
            $totalVolKg += $kg;
        }

        return (float) round($totalVolKg, 3);
    }

    /**
     * Compute gross weight (kg) from the items list.
     *
     * Items may include:
     *  - 'weight' (kg) and 'quantity'
     *  - OR 'variant_id' and 'quantity' (weight will be looked up in product_variants table)
     *
     * Returns total weight in kg (float).
     */
    protected function computeWeightFromItems(array $items): float
    {
        $totalKg = 0.0;
        $variantIds = [];
        $pendingVariantQty = [];

        // First pass: sum explicit weights and collect variant ids for lookup
        foreach ($items as $i => $item) {
            $qty = max(1, (int)($item['quantity'] ?? 1));

            // if item has weight explicitly provided (kg)
            if (isset($item['weight']) && is_numeric($item['weight'])) {
                $w = (float)$item['weight'];
                if ($w > 0) {
                    $totalKg += $w * $qty;
                    continue;
                }
            }

            // otherwise attempt to resolve using variant_id
            $variantId = $item['variant_id'] ?? $item['product_variant_id'] ?? null;
            if ($variantId) {
                $variantIds[] = (int)$variantId;
                // record qty to apply once weights are fetched
                if (!isset($pendingVariantQty[$variantId])) $pendingVariantQty[$variantId] = 0;
                $pendingVariantQty[$variantId] += $qty;
                continue;
            }

            // no weight and no variant -> can't derive weight for this line
            Log::channel('shippingcost')->debug('Item missing weight and variant_id', ['item' => $item]);
        }

        // Bulk fetch variant weights to avoid N+1
        if (!empty($variantIds)) {
            $variantIds = array_values(array_unique($variantIds));
            $weights = ProductVariant::whereIn('id', $variantIds)
                        ->pluck('weight', 'id')
                        ->mapWithKeys(function ($value, $key) {
                            // Ensure numeric cast
                            return [$key => (float)$value];
                        })->toArray();

            foreach ($pendingVariantQty as $vid => $qty) {
                $w = Arr::get($weights, $vid, 0.0);
                if ($w > 0) {
                    $totalKg += ((float)$w * (int)$qty);
                } else {
                    Log::channel('shippingcost')->debug("Variant weight not found or zero", ['variant_id' => $vid, 'weight' => $w]);
                }
            }
        }

        return (float) round($totalKg, 3);
    }

    protected function resolveZoneForState(int $stateId): ?int
    {
//        $zone = ShippingZone::whereHas('states', function ($q) use ($stateId) {
//            $q->where('id', $stateId);
//        })->first();

        $state = State::find($stateId);

        $zone = $state->shippingZone()->first();

        return $zone ? $zone->id : null;
    }

    protected function getApplicableRates(int $shippingMethodId, $shippingZoneId = null, Carbon $now, float $subtotal = 0.0, float $weightKg = 0.0): Collection
    {
        $query = ShippingRate::query()
            ->where('shipping_method_id', $shippingMethodId)
            ->where('is_active', true)
            ->where(function ($q) use ($shippingZoneId) {
                if (is_null($shippingZoneId)) {
                    // prefer zone-less and zone-specific; allow both so we can pick best later
                    $q->whereNull('shipping_zone_id');
                } else {
                    $q->where('shipping_zone_id', $shippingZoneId);
                }
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });

        return $query->get();
    }

    protected function selectBestRate(Collection $rates, float $weightKg, float $subtotal): ShippingRate
    {
        $scored = $rates->map(function (ShippingRate $rate) use ($weightKg, $subtotal) {
            $score = 0;

            if (!is_null($rate->min_weight) || !is_null($rate->max_weight)) {
                $minW = $rate->min_weight ? (float)$rate->min_weight : -INF;
                $maxW = $rate->max_weight ? (float)$rate->max_weight : INF;
                if ($weightKg >= $minW && $weightKg <= $maxW) {
                    $score += 100;
                } else {
                    $score -= 10;
                }
            }

            if (!is_null($rate->min_subtotal) || !is_null($rate->max_subtotal)) {
                $minS = $rate->min_subtotal ? (float)$rate->min_subtotal : -INF;
                $maxS = $rate->max_subtotal ? (float)$rate->max_subtotal : INF;
                if ($subtotal >= $minS && $subtotal <= $maxS) {
                    $score += 50;
                } else {
                    $score -= 5;
                }
            }

            if (strtolower($rate->rate_type) === 'hybrid') $score += 5;

            $score -= (float)$rate->surcharge / 1000.0;

            return ['rate' => $rate, 'score' => $score];
        });

        $sorted = $scored->sortByDesc('score')->values();

        return $sorted->first()['rate'];
    }

    protected function calculateByRateType(ShippingRate $rate, float $weightKg, float $subtotal): array
    {
        $baseRate = (float) $rate->base_rate;
        $perKg = (float) $rate->per_kg;
        $total = 0.0;
        $perKgTotal = 0.0;

        switch ($rate->rate_type) {
            case 'flat':
                $total = $baseRate;
                break;

            case 'per_kg':
                $perKgTotal = round($perKg * $weightKg, 2);
                $total = $perKgTotal;
                break;

            case 'hybrid':
            default:
                $perKgTotal = round($perKg * $weightKg, 2);
                $total = round($baseRate + $perKgTotal, 2);
                break;
        }

        return [
            'base_rate' => round($baseRate, 2),
            'per_kg' => round($perKg, 2),
            'weight_kg' => round($weightKg, 3),
            'per_kg_total' => $perKgTotal,
            'total' => round($total, 2),
        ];
    }
}
