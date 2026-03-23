<?php

namespace App\Services\Shipping;

use App\Exceptions\ShippingRateNotFoundException;
use App\Models\PickupLocation;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\State;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ShippingCostService
{
    public function calculate(array $payload): array
    {
        if (!isset($payload['shipping_method_id'])) {
            throw new \InvalidArgumentException('shipping_method_id is required');
        }

        $method = $this->resolveMethod((int) $payload['shipping_method_id']);
        if (!$method || !$method->is_active) {
            throw new ShippingRateNotFoundException('The selected shipping method is not available.');
        }

        $subtotal = isset($payload['subtotal']) ? (float) $payload['subtotal'] : 0.0;
        $weightKg = isset($payload['weight_kg']) ? (float) $payload['weight_kg'] : (float) ($payload['weight'] ?? 0.0);
        $shippingZoneId = !empty($payload['shipping_zone_id']) ? (int) $payload['shipping_zone_id'] : null;
        $stateId = !empty($payload['state_id']) ? (int) $payload['state_id'] : null;
        $lgaId = !empty($payload['lga_id']) ? (int) $payload['lga_id'] : null;
        $pickupLocationId = !empty($payload['pickup_location_id']) ? (int) $payload['pickup_location_id'] : null;
        $volumetricDivisor = (float) ($payload['volumetric_divisor'] ?? 5000.0);
        $useVolumetric = (bool) ($payload['use_volumetric'] ?? true);
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        if ($shippingZoneId === null && $stateId !== null) {
            $shippingZoneId = $this->resolveZoneForState($stateId);
        }

        if ($this->isPickupMethod($method)) {
            return $this->calculatePickup($method, $pickupLocationId, $stateId, $shippingZoneId);
        }

        if ($useVolumetric && !empty($items)) {
            $volumetricWeight = $this->computeVolumetricWeight($items, $volumetricDivisor);
            $weightKg = max($weightKg, $volumetricWeight);
        }

        if ($weightKg <= 0.0 && !empty($items)) {
            $weightKg = max($weightKg, $this->computeWeightFromItems($items));
        }

        $rates = ShippingRate::query()
            ->with(['method:id,name,method_type', 'zone:id,name', 'state:id,name', 'lga:id,name'])
            ->where('shipping_method_id', $method->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->get()
            ->filter(fn (ShippingRate $rate) => $this->rateMatchesScope($rate, $shippingZoneId, $stateId, $lgaId))
            ->filter(fn (ShippingRate $rate) => $this->rateMatchesThresholds($rate, $subtotal, $weightKg))
            ->values();

        if ($rates->isEmpty()) {
            throw new ShippingRateNotFoundException('No shipping rate is configured for the selected method and location.');
        }

        $rate = $this->selectBestRate($rates, $shippingZoneId, $stateId, $lgaId);
        $calculation = $this->calculateByRateType($rate, $weightKg);
        $surcharge = (float) $rate->surcharge;

        $calculation['surcharge'] = round($surcharge, 2);
        $calculation['total_before_free_check'] = round($calculation['total'] + $surcharge, 2);

        if (!is_null($rate->free_shipping_threshold) && $subtotal >= (float) $rate->free_shipping_threshold) {
            $calculation['total'] = 0.00;
            $calculation['free_shipping_applied'] = true;
        } else {
            $calculation['total'] = round(max($calculation['total'] + $surcharge, 0), 2);
            $calculation['free_shipping_applied'] = false;
        }

        $calculation['currency'] = $rate->currency ?? 'NGN';
        $calculation['rate_id'] = $rate->id;
        $calculation['shipping_method_id'] = $method->id;
        $calculation['shipping_zone_id'] = $rate->shipping_zone_id ?? $shippingZoneId;
        $calculation['state_id'] = $rate->state_id ?? $stateId;
        $calculation['lga_id'] = $rate->lga_id ?? $lgaId;
        $calculation['used_weight_kg'] = round($weightKg, 3);
        $calculation['rate_type'] = $rate->rate_type;
        $calculation['estimated_delivery_text'] = $rate->estimated_delivery_text;
        $calculation['method_type'] = $method->method_type;

        return $calculation;
    }

    public function listActiveMethods(): EloquentCollection
    {
        return ShippingMethod::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function listPickupLocationsForState(?int $stateId, ?int $shippingMethodId = null): EloquentCollection
    {
        if (!$stateId) {
            return new EloquentCollection();
        }

        $zoneId = $this->resolveZoneForState($stateId);

        return PickupLocation::query()
            ->with(['method:id,name,method_type', 'zone:id,name'])
            ->where('is_active', true)
            ->when($shippingMethodId, fn ($query) => $query->where('shipping_method_id', $shippingMethodId))
            ->where(function ($query) use ($stateId, $zoneId) {
                $query->where('state_id', $stateId);

                if ($zoneId) {
                    $query->orWhere('shipping_zone_id', $zoneId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    public function resolveZoneForState(int $stateId): ?int
    {
        $state = State::query()->find($stateId);

        return $state?->shippingZone()->first()?->id;
    }

    public function resolveMethod(?int $shippingMethodId): ?ShippingMethod
    {
        if (!$shippingMethodId) {
            return null;
        }

        return ShippingMethod::query()->find($shippingMethodId);
    }

    public function isPickupMethod(ShippingMethod|int|null $method): bool
    {
        if (is_int($method)) {
            $method = $this->resolveMethod($method);
        }

        return $method?->isPickup() ?? false;
    }

    public function pickupLocationMatchesState(PickupLocation $pickupLocation, ?int $stateId): bool
    {
        if (!$stateId) {
            return true;
        }

        if ($pickupLocation->state_id && (int) $pickupLocation->state_id === $stateId) {
            return true;
        }

        $zoneId = $this->resolveZoneForState($stateId);

        return $zoneId !== null && (int) $pickupLocation->shipping_zone_id === $zoneId;
    }

    protected function calculatePickup(ShippingMethod $method, ?int $pickupLocationId, ?int $stateId, ?int $shippingZoneId): array
    {
        $pickupLocation = null;

        if ($pickupLocationId) {
            $pickupLocation = PickupLocation::query()
                ->whereKey($pickupLocationId)
                ->where('shipping_method_id', $method->id)
                ->where('is_active', true)
                ->first();

            if (!$pickupLocation) {
                throw new ShippingRateNotFoundException('The selected pickup location is not available.');
            }

            if (!$this->pickupLocationMatchesState($pickupLocation, $stateId)) {
                throw new ShippingRateNotFoundException('The selected pickup location is not valid for the chosen state.');
            }
        }

        return [
            'base_rate' => 0.0,
            'per_kg' => 0.0,
            'weight_kg' => 0.0,
            'per_kg_total' => 0.0,
            'total' => 0.0,
            'surcharge' => 0.0,
            'total_before_free_check' => 0.0,
            'free_shipping_applied' => false,
            'currency' => 'NGN',
            'rate_id' => null,
            'shipping_method_id' => $method->id,
            'shipping_zone_id' => $pickupLocation?->shipping_zone_id ?? $shippingZoneId,
            'state_id' => $pickupLocation?->state_id ?? $stateId,
            'lga_id' => $pickupLocation?->lga_id,
            'used_weight_kg' => 0.0,
            'rate_type' => 'flat',
            'estimated_delivery_text' => null,
            'method_type' => $method->method_type,
        ];
    }

    protected function computeVolumetricWeight(array $items, float $divisor = 5000.0): float
    {
        $totalVolKg = 0.0;

        foreach ($items as $item) {
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $length = (float) ($item['length_cm'] ?? 0.0);
            $width = (float) ($item['width_cm'] ?? 0.0);
            $height = (float) ($item['height_cm'] ?? 0.0);

            if ($length <= 0 || $width <= 0 || $height <= 0) {
                continue;
            }

            $totalVolKg += (($length * $width * $height) * $qty) / $divisor;
        }

        return (float) round($totalVolKg, 3);
    }

    protected function computeWeightFromItems(array $items): float
    {
        $totalKg = 0.0;
        $variantIds = [];
        $pendingVariantQty = [];

        foreach ($items as $item) {
            $qty = max(1, (int) ($item['quantity'] ?? 1));

            if (isset($item['weight']) && is_numeric($item['weight'])) {
                $weight = (float) $item['weight'];

                if ($weight > 0) {
                    $totalKg += $weight * $qty;
                    continue;
                }
            }

            $variantId = $item['variant_id'] ?? $item['product_variant_id'] ?? null;
            if ($variantId) {
                $variantIds[] = (int) $variantId;
                $pendingVariantQty[$variantId] = ($pendingVariantQty[$variantId] ?? 0) + $qty;
                continue;
            }

            Log::channel('shippingcost')->debug('Item missing weight and variant_id', ['item' => $item]);
        }

        if (!empty($variantIds)) {
            $weights = ProductVariant::query()
                ->whereIn('id', array_values(array_unique($variantIds)))
                ->pluck('weight', 'id')
                ->mapWithKeys(fn ($value, $key) => [$key => (float) $value])
                ->all();

            foreach ($pendingVariantQty as $variantId => $qty) {
                $weight = Arr::get($weights, $variantId, 0.0);
                if ($weight > 0) {
                    $totalKg += $weight * (int) $qty;
                }
            }
        }

        return (float) round($totalKg, 3);
    }

    protected function rateMatchesScope(ShippingRate $rate, ?int $shippingZoneId, ?int $stateId, ?int $lgaId): bool
    {
        if ($rate->lga_id !== null) {
            return $lgaId !== null && (int) $rate->lga_id === $lgaId;
        }

        if ($rate->state_id !== null) {
            return $stateId !== null && (int) $rate->state_id === $stateId;
        }

        if ($rate->shipping_zone_id !== null) {
            return $shippingZoneId !== null && (int) $rate->shipping_zone_id === $shippingZoneId;
        }

        return true;
    }

    protected function rateMatchesThresholds(ShippingRate $rate, float $subtotal, float $weightKg): bool
    {
        if ($rate->min_weight !== null && $weightKg < (float) $rate->min_weight) {
            return false;
        }

        if ($rate->max_weight !== null && $weightKg > (float) $rate->max_weight) {
            return false;
        }

        if ($rate->min_subtotal !== null && $subtotal < (float) $rate->min_subtotal) {
            return false;
        }

        if ($rate->max_subtotal !== null && $subtotal > (float) $rate->max_subtotal) {
            return false;
        }

        return true;
    }

    protected function selectBestRate(Collection $rates, ?int $shippingZoneId, ?int $stateId, ?int $lgaId): ShippingRate
    {
        return $rates
            ->sort(function (ShippingRate $left, ShippingRate $right) use ($shippingZoneId, $stateId, $lgaId) {
                $leftScope = $this->scopePriority($left, $shippingZoneId, $stateId, $lgaId);
                $rightScope = $this->scopePriority($right, $shippingZoneId, $stateId, $lgaId);

                if ($leftScope !== $rightScope) {
                    return $rightScope <=> $leftScope;
                }

                $leftConstraintScore = $this->constraintPriority($left);
                $rightConstraintScore = $this->constraintPriority($right);

                if ($leftConstraintScore !== $rightConstraintScore) {
                    return $rightConstraintScore <=> $leftConstraintScore;
                }

                if ((int) $left->sort_order !== (int) $right->sort_order) {
                    return (int) $left->sort_order <=> (int) $right->sort_order;
                }

                return (int) $left->id <=> (int) $right->id;
            })
            ->firstOrFail();
    }

    protected function scopePriority(ShippingRate $rate, ?int $shippingZoneId, ?int $stateId, ?int $lgaId): int
    {
        if ($rate->lga_id !== null && $lgaId !== null && (int) $rate->lga_id === $lgaId) {
            return 4;
        }

        if ($rate->state_id !== null && $stateId !== null && (int) $rate->state_id === $stateId) {
            return 3;
        }

        if ($rate->shipping_zone_id !== null && $shippingZoneId !== null && (int) $rate->shipping_zone_id === $shippingZoneId) {
            return 2;
        }

        if ($rate->shipping_zone_id === null && $rate->state_id === null && $rate->lga_id === null) {
            return 1;
        }

        return 0;
    }

    protected function constraintPriority(ShippingRate $rate): int
    {
        $score = 0;

        foreach (['min_weight', 'max_weight', 'min_subtotal', 'max_subtotal'] as $field) {
            if ($rate->{$field} !== null) {
                $score++;
            }
        }

        return $score;
    }

    protected function calculateByRateType(ShippingRate $rate, float $weightKg): array
    {
        $baseRate = (float) $rate->base_rate;
        $perKg = (float) $rate->per_kg;
        $perKgTotal = 0.0;

        $total = match ($rate->rate_type) {
            'flat' => $baseRate,
            'per_kg' => $perKgTotal = round($perKg * $weightKg, 2),
            default => round($baseRate + ($perKgTotal = round($perKg * $weightKg, 2)), 2),
        };

        return [
            'base_rate' => round($baseRate, 2),
            'per_kg' => round($perKg, 2),
            'weight_kg' => round($weightKg, 3),
            'per_kg_total' => $perKgTotal,
            'total' => round(max($total, 0), 2),
        ];
    }
}
