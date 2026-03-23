<?php

namespace App\Services;

use App\Models\Lga;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\State;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShippingManagementService
{
    public function listMethods(array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $type = trim((string) ($filters['type'] ?? ''));

        return ShippingMethod::query()
            ->withCount(['rates', 'pickupLocations'])
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->when($status !== '', fn (Builder $query) => $query->where('is_active', $status === 'active'))
            ->when($type !== '', fn (Builder $query) => $query->where('method_type', $type))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();
    }

    public function listRates(array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $methodId = !empty($filters['method_id']) ? (int) $filters['method_id'] : null;
        $scope = trim((string) ($filters['scope'] ?? ''));

        return ShippingRate::query()
            ->with(['method:id,name,method_type', 'zone:id,name', 'state:id,name', 'lga:id,name'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $nested) use ($search) {
                    $nested->whereHas('method', fn (Builder $methodQuery) => $methodQuery->where('name', 'like', "%{$search}%"))
                        ->orWhere('estimated_delivery_text', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', function (Builder $query) use ($status) {
                $now = now();

                match ($status) {
                    'active' => $query
                        ->where('is_active', true)
                        ->where(fn (Builder $nested) => $nested->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                        ->where(fn (Builder $nested) => $nested->whereNull('ends_at')->orWhere('ends_at', '>=', $now)),
                    'inactive' => $query->where('is_active', false),
                    'scheduled' => $query->where('is_active', true)->where('starts_at', '>', $now),
                    'expired' => $query->whereNotNull('ends_at')->where('ends_at', '<', $now),
                    default => null,
                };
            })
            ->when($methodId, fn (Builder $query) => $query->where('shipping_method_id', $methodId))
            ->when($scope !== '', function (Builder $query) use ($scope) {
                match ($scope) {
                    'global' => $query->whereNull('shipping_zone_id')->whereNull('state_id')->whereNull('lga_id'),
                    'zone' => $query->whereNotNull('shipping_zone_id')->whereNull('state_id')->whereNull('lga_id'),
                    'state' => $query->whereNotNull('state_id')->whereNull('lga_id'),
                    'lga' => $query->whereNotNull('lga_id'),
                    default => null,
                };
            })
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();
    }

    public function formOptionsForMethods(): array
    {
        return [
            'methodTypes' => $this->methodTypeOptions(),
        ];
    }

    public function formOptionsForRates(?int $stateId = null): array
    {
        return [
            'methods' => ShippingMethod::query()
                ->select(['id', 'name', 'method_type', 'is_active'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (ShippingMethod $method) => [
                    'id' => (int) $method->id,
                    'name' => $method->name,
                    'method_type' => $method->method_type,
                    'is_active' => (bool) $method->is_active,
                ])
                ->values()
                ->all(),
            'zones' => ShippingZone::query()
                ->withCount('states')
                ->orderBy('name')
                ->get()
                ->map(fn (ShippingZone $zone) => [
                    'id' => (int) $zone->id,
                    'name' => $zone->name,
                    'state_count' => (int) ($zone->states_count ?? 0),
                ])
                ->values()
                ->all(),
            'states' => State::query()
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(fn (State $state) => [
                    'id' => (int) $state->id,
                    'name' => $state->name,
                ])
                ->values()
                ->all(),
            'lgas' => $this->lgasForState($stateId),
            'scopeTypes' => [
                ['value' => 'global', 'label' => 'Global'],
                ['value' => 'zone', 'label' => 'Zone'],
                ['value' => 'state', 'label' => 'State'],
                ['value' => 'lga', 'label' => 'LGA'],
            ],
            'rateTypes' => [
                ['value' => 'flat', 'label' => 'Flat rate'],
                ['value' => 'per_kg', 'label' => 'Per kg'],
                ['value' => 'hybrid', 'label' => 'Flat + per kg'],
            ],
        ];
    }

    public function createMethod(array $data): ShippingMethod
    {
        return $this->persistMethod(new ShippingMethod(), $data);
    }

    public function updateMethod(ShippingMethod $method, array $data): ShippingMethod
    {
        return $this->persistMethod($method, $data);
    }

    public function createRate(array $data): ShippingRate
    {
        return $this->persistRate(new ShippingRate(), $data);
    }

    public function updateRate(ShippingRate $rate, array $data): ShippingRate
    {
        return $this->persistRate($rate, $data);
    }

    public function toMethodListPayload(ShippingMethod $method): array
    {
        return [
            'id' => (int) $method->id,
            'name' => $method->name,
            'description' => $method->description,
            'method_type' => $method->method_type,
            'sort_order' => (int) $method->sort_order,
            'is_active' => (bool) $method->is_active,
            'rate_count' => (int) ($method->rates_count ?? 0),
            'pickup_location_count' => (int) ($method->pickup_locations_count ?? 0),
        ];
    }

    public function toMethodFormPayload(ShippingMethod $method): array
    {
        return [
            'id' => (int) $method->id,
            'name' => $method->name,
            'description' => $method->description,
            'method_type' => $method->method_type,
            'sort_order' => (int) $method->sort_order,
            'is_active' => (bool) $method->is_active,
        ];
    }

    public function toRateListPayload(ShippingRate $rate): array
    {
        return [
            'id' => (int) $rate->id,
            'method' => [
                'id' => (int) $rate->shipping_method_id,
                'name' => $rate->method?->name,
                'method_type' => $rate->method?->method_type,
            ],
            'scope' => $this->scopeLabel($rate),
            'scope_meta' => $this->scopeMeta($rate),
            'rate_type' => $rate->rate_type,
            'base_rate' => (float) $rate->base_rate,
            'per_kg' => (float) $rate->per_kg,
            'surcharge' => (float) $rate->surcharge,
            'free_shipping_threshold' => $rate->free_shipping_threshold !== null ? (float) $rate->free_shipping_threshold : null,
            'estimated_delivery_text' => $rate->estimated_delivery_text,
            'sort_order' => (int) $rate->sort_order,
            'is_active' => (bool) $rate->is_active,
            'status' => $this->statusLabel($rate),
            'starts_at' => optional($rate->starts_at)?->toIso8601String(),
            'ends_at' => optional($rate->ends_at)?->toIso8601String(),
            'min_weight' => $rate->min_weight !== null ? (float) $rate->min_weight : null,
            'max_weight' => $rate->max_weight !== null ? (float) $rate->max_weight : null,
            'min_subtotal' => $rate->min_subtotal !== null ? (float) $rate->min_subtotal : null,
            'max_subtotal' => $rate->max_subtotal !== null ? (float) $rate->max_subtotal : null,
        ];
    }

    public function toRateFormPayload(ShippingRate $rate): array
    {
        return [
            'id' => (int) $rate->id,
            'shipping_method_id' => (int) $rate->shipping_method_id,
            'scope_type' => $this->scopeType($rate),
            'shipping_zone_id' => $rate->shipping_zone_id ? (int) $rate->shipping_zone_id : null,
            'state_id' => $rate->state_id ? (int) $rate->state_id : null,
            'lga_id' => $rate->lga_id ? (int) $rate->lga_id : null,
            'rate_type' => $rate->rate_type,
            'base_rate' => (float) $rate->base_rate,
            'per_kg' => (float) $rate->per_kg,
            'surcharge' => (float) $rate->surcharge,
            'free_shipping_threshold' => $rate->free_shipping_threshold !== null ? (float) $rate->free_shipping_threshold : null,
            'estimated_delivery_text' => $rate->estimated_delivery_text,
            'min_weight' => $rate->min_weight !== null ? (float) $rate->min_weight : null,
            'max_weight' => $rate->max_weight !== null ? (float) $rate->max_weight : null,
            'min_subtotal' => $rate->min_subtotal !== null ? (float) $rate->min_subtotal : null,
            'max_subtotal' => $rate->max_subtotal !== null ? (float) $rate->max_subtotal : null,
            'sort_order' => (int) $rate->sort_order,
            'starts_at' => optional($rate->starts_at)?->format('Y-m-d\TH:i'),
            'ends_at' => optional($rate->ends_at)?->format('Y-m-d\TH:i'),
            'is_active' => (bool) $rate->is_active,
        ];
    }

    public function lgasForState(?int $stateId): array
    {
        if (!$stateId) {
            return [];
        }

        return Lga::query()
            ->where('state_id', $stateId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Lga $lga) => [
                'id' => (int) $lga->id,
                'name' => $lga->name,
            ])
            ->values()
            ->all();
    }

    protected function persistMethod(ShippingMethod $method, array $data): ShippingMethod
    {
        $method->fill([
            'name' => trim((string) $data['name']),
            'description' => $this->nullableString($data['description'] ?? null),
            'method_type' => $data['method_type'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $method->save();
        $this->clearShippingCaches();

        return $method->refresh();
    }

    protected function persistRate(ShippingRate $rate, array $data): ShippingRate
    {
        return DB::transaction(function () use ($rate, $data) {
            $normalized = $this->normalizeRatePayload($data);

            $this->assertNoConflictingRate($rate, $normalized);

            $rate->fill($normalized);
            $rate->save();

            $this->clearShippingCaches();

            return $rate->refresh(['method:id,name,method_type', 'zone:id,name', 'state:id,name', 'lga:id,name']);
        });
    }

    protected function normalizeRatePayload(array $data): array
    {
        $scopeType = $data['scope_type'];
        $method = ShippingMethod::query()->findOrFail((int) $data['shipping_method_id']);
        $isPickup = $method->isPickup();

        $shippingZoneId = null;
        $stateId = null;
        $lgaId = null;

        if ($scopeType === 'zone') {
            $shippingZoneId = (int) $data['shipping_zone_id'];
        } elseif ($scopeType === 'state') {
            $stateId = (int) $data['state_id'];
        } elseif ($scopeType === 'lga') {
            $stateId = (int) $data['state_id'];
            $lgaId = (int) $data['lga_id'];
        }

        return [
            'shipping_method_id' => (int) $data['shipping_method_id'],
            'shipping_zone_id' => $shippingZoneId,
            'state_id' => $stateId,
            'lga_id' => $lgaId,
            'rate_type' => $isPickup ? 'flat' : $data['rate_type'],
            'base_rate' => $isPickup ? 0 : (float) $data['base_rate'],
            'per_kg' => $isPickup ? 0 : (float) ($data['per_kg'] ?? 0),
            'surcharge' => $isPickup ? 0 : (float) ($data['surcharge'] ?? 0),
            'free_shipping_threshold' => $isPickup ? null : $this->nullableFloat($data['free_shipping_threshold'] ?? null),
            'estimated_delivery_text' => $this->nullableString($data['estimated_delivery_text'] ?? null),
            'currency' => 'NGN',
            'min_weight' => $isPickup ? null : $this->nullableFloat($data['min_weight'] ?? null),
            'max_weight' => $isPickup ? null : $this->nullableFloat($data['max_weight'] ?? null),
            'min_subtotal' => $this->nullableFloat($data['min_subtotal'] ?? null),
            'max_subtotal' => $this->nullableFloat($data['max_subtotal'] ?? null),
            'starts_at' => $this->nullableString($data['starts_at'] ?? null),
            'ends_at' => $this->nullableString($data['ends_at'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    protected function assertNoConflictingRate(ShippingRate $rate, array $normalized): void
    {
        $conflict = ShippingRate::query()
            ->where('shipping_method_id', $normalized['shipping_method_id'])
            ->when($rate->exists, fn (Builder $query) => $query->whereKeyNot($rate->id))
            ->where('shipping_zone_id', $normalized['shipping_zone_id'])
            ->where('state_id', $normalized['state_id'])
            ->where('lga_id', $normalized['lga_id'])
            ->get()
            ->first(function (ShippingRate $existing) use ($normalized) {
                return $this->rangesOverlap($existing->min_weight, $existing->max_weight, $normalized['min_weight'], $normalized['max_weight'])
                    && $this->rangesOverlap($existing->min_subtotal, $existing->max_subtotal, $normalized['min_subtotal'], $normalized['max_subtotal'])
                    && $this->dateRangesOverlap($existing->starts_at?->toDateTimeString(), $existing->ends_at?->toDateTimeString(), $normalized['starts_at'], $normalized['ends_at']);
            });

        if ($conflict) {
            throw ValidationException::withMessages([
                'scope_type' => 'Another shipping rate already overlaps this method, location, schedule, and subtotal/weight band.',
            ]);
        }
    }

    protected function rangesOverlap($existingMin, $existingMax, $newMin, $newMax): bool
    {
        $leftMin = $existingMin !== null ? (float) $existingMin : -INF;
        $leftMax = $existingMax !== null ? (float) $existingMax : INF;
        $rightMin = $newMin !== null ? (float) $newMin : -INF;
        $rightMax = $newMax !== null ? (float) $newMax : INF;

        return $leftMin <= $rightMax && $rightMin <= $leftMax;
    }

    protected function dateRangesOverlap(?string $existingStartsAt, ?string $existingEndsAt, ?string $newStartsAt, ?string $newEndsAt): bool
    {
        $leftStart = $existingStartsAt ? strtotime($existingStartsAt) : PHP_INT_MIN;
        $leftEnd = $existingEndsAt ? strtotime($existingEndsAt) : PHP_INT_MAX;
        $rightStart = $newStartsAt ? strtotime($newStartsAt) : PHP_INT_MIN;
        $rightEnd = $newEndsAt ? strtotime($newEndsAt) : PHP_INT_MAX;

        return $leftStart <= $rightEnd && $rightStart <= $leftEnd;
    }

    protected function statusLabel(ShippingRate $rate): string
    {
        $now = now();

        if (!$rate->is_active) {
            return 'Inactive';
        }

        if ($rate->starts_at && $rate->starts_at->isFuture()) {
            return 'Scheduled';
        }

        if ($rate->ends_at && $rate->ends_at->isPast()) {
            return 'Expired';
        }

        return 'Active';
    }

    protected function scopeType(ShippingRate $rate): string
    {
        if ($rate->lga_id) {
            return 'lga';
        }

        if ($rate->state_id) {
            return 'state';
        }

        if ($rate->shipping_zone_id) {
            return 'zone';
        }

        return 'global';
    }

    protected function scopeLabel(ShippingRate $rate): string
    {
        return match ($this->scopeType($rate)) {
            'lga' => 'LGA',
            'state' => 'State',
            'zone' => 'Zone',
            default => 'Global',
        };
    }

    protected function scopeMeta(ShippingRate $rate): ?string
    {
        return match ($this->scopeType($rate)) {
            'lga' => collect([$rate->lga?->name, $rate->state?->name])->filter()->join(', '),
            'state' => $rate->state?->name,
            'zone' => $rate->zone?->name,
            default => 'All locations',
        };
    }

    protected function methodTypeOptions(): array
    {
        return [
            ['value' => ShippingMethod::TYPE_DELIVERY, 'label' => 'Delivery'],
            ['value' => ShippingMethod::TYPE_PICKUP, 'label' => 'Pickup'],
        ];
    }

    protected function clearShippingCaches(): void
    {
        Cache::forget('checkout:shipping_methods');
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    protected function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
