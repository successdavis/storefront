<?php

namespace App\Services;

use App\Exceptions\ShippingRateNotFoundException;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\Warehouse;
use App\Services\Shipping\ShippingCostService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeliveryEstimateService
{
    public function __construct(
        protected ShippingCostService $shippingCostService,
        protected DeliveryDateFormatter $deliveryDateFormatter,
    ) {}

    public function estimateManyForVariantIds(array $variantIds, ?array $destination = null, array $options = []): array
    {
        $variantIds = collect($variantIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($variantIds)) {
            return [];
        }

        $variants = ProductVariant::query()
            ->with('product:id,name')
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $warehouses = $this->resolveBestWarehousesForVariants($variantIds, $destination ?? []);
        $estimates = [];

        foreach ($variantIds as $variantId) {
            $variant = $variants->get($variantId);
            if (!$variant) {
                continue;
            }

            $estimates[$variantId] = $this->estimateForVariant(
                $variant,
                $destination ?? [],
                $options,
                $warehouses[$variantId] ?? null,
            );
        }

        return $estimates;
    }

    public function estimateForVariantId(?int $variantId, ?array $destination = null, array $options = []): array
    {
        if (!$variantId) {
            return $this->unavailableEstimate(
                reason: 'missing_variant',
                destinationLabel: $this->destinationLabel($destination ?? []),
            );
        }

        return $this->estimateManyForVariantIds([$variantId], $destination, $options)[$variantId]
            ?? $this->unavailableEstimate(
                reason: 'missing_variant',
                destinationLabel: $this->destinationLabel($destination ?? []),
            );
    }

    public function estimateForCheckoutItems(array $items, ?array $destination = null, array $options = []): array
    {
        $normalizedItems = collect($items)
            ->map(function (array $item) {
                return [
                    'variant_id' => (int) ($item['variant_id'] ?? data_get($item, 'variant.id') ?? 0),
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                ];
            })
            ->filter(fn (array $item) => $item['variant_id'] > 0)
            ->values();

        if ($normalizedItems->isEmpty()) {
            return $this->unavailableEstimate(
                reason: 'missing_items',
                destinationLabel: $this->destinationLabel($destination ?? []),
                scope: 'checkout',
            );
        }

        $variantIds = $normalizedItems->pluck('variant_id')->all();
        $variants = ProductVariant::query()
            ->with('product:id,name')
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');
        $warehouses = $this->resolveBestWarehousesForVariants($variantIds, $destination ?? []);

        $lineEstimates = $normalizedItems->map(function (array $item) use ($variants, $destination, $options, $warehouses) {
            $variant = $variants->get($item['variant_id']);
            if (!$variant) {
                return null;
            }

            return $this->estimateForVariant(
                $variant,
                $destination ?? [],
                array_merge($options, [
                    'quantity' => $item['quantity'],
                ]),
                $warehouses[$variant->id] ?? null,
            );
        })->filter(fn ($estimate) => is_array($estimate) && ($estimate['available'] ?? false))->values();

        if ($lineEstimates->isEmpty()) {
            return $this->unavailableEstimate(
                reason: 'missing_destination',
                destinationLabel: $this->destinationLabel($destination ?? []),
                scope: 'checkout',
            );
        }

        $earliest = collect($lineEstimates)->pluck('earliest_date')->filter()->max();
        $latest = collect($lineEstimates)->pluck('latest_date')->filter()->max();
        $destinationLabel = $this->destinationLabel($destination ?? []);
        $methodType = collect($lineEstimates)->pluck('method.type')->first() ?? ShippingMethod::TYPE_DELIVERY;
        $window = $this->deliveryDateFormatter->formatWindow(
            $earliest ? CarbonImmutable::parse($earliest) : null,
            $latest ? CarbonImmutable::parse($latest) : null,
        );

        if (!$window) {
            return $this->unavailableEstimate(
                reason: 'timing_unavailable',
                destinationLabel: $destinationLabel,
                scope: 'checkout',
            );
        }

        return [
            'available' => true,
            'reason' => null,
            'scope' => 'checkout',
            'destination_label' => $destinationLabel,
            'earliest_date' => $window['earliest_date'],
            'latest_date' => $window['latest_date'],
            'window' => $window,
            'method' => [
                'type' => $methodType,
            ],
            'storefront_message' => $this->deliveryDateFormatter->buildStorefrontMessage($methodType, $destinationLabel, $window),
            'checkout_message' => $this->deliveryDateFormatter->buildCheckoutMessage($methodType, $window),
            'warehouse' => collect($lineEstimates)->pluck('warehouse')->filter()->first(),
            'line_estimates' => $lineEstimates->all(),
        ];
    }

    protected function estimateForVariant(ProductVariant $variant, array $destination, array $options, ?Warehouse $warehouse = null): array
    {
        $scope = (string) ($options['scope'] ?? 'storefront');
        $destinationLabel = $this->destinationLabel($destination);
        $method = $this->resolveMethod($options['shipping_method_id'] ?? null, $scope);

        if (!$method) {
            return $this->unavailableEstimate(
                reason: 'missing_method',
                destinationLabel: $destinationLabel,
                scope: $scope,
            );
        }

        if ($this->shippingCostService->isPickupMethod($method)) {
            $pickupLocationId = !empty($options['pickup_location_id']) ? (int) $options['pickup_location_id'] : null;
            if (!$pickupLocationId) {
                return $this->unavailableEstimate(
                    reason: 'missing_pickup_location',
                    destinationLabel: $destinationLabel,
                    scope: $scope,
                    method: $method,
                );
            }

            $pickupLocation = $this->shippingCostService->resolvePickupLocation($pickupLocationId, $method->id);
            if (!$pickupLocation) {
                return $this->unavailableEstimate(
                    reason: 'timing_unavailable',
                    destinationLabel: $destinationLabel,
                    scope: $scope,
                    method: $method,
                );
            }

            $timing = $this->resolveTiming($method, null);
            if (!$this->hasTimingConfig($timing)) {
                $leadDays = (int) ceil(max((int) ($pickupLocation->lead_time_hours ?? 0), 0) / 24);
                $timing['processing_days_min'] = $leadDays;
                $timing['processing_days_max'] = $leadDays;
                $timing['transit_days_min'] = 0;
                $timing['transit_days_max'] = 0;
            }

            $window = $this->computeWindow($timing, $method, null);

            if (!$window) {
                return $this->unavailableEstimate(
                    reason: 'timing_unavailable',
                    destinationLabel: $pickupLocation->name,
                    scope: $scope,
                    method: $method,
                );
            }

            return $this->buildEstimatePayload(
                method: $method,
                window: $window,
                destinationLabel: $pickupLocation->name,
                warehouse: $warehouse,
                scope: $scope,
            );
        }

        if (empty($destination['state_id']) && empty($destination['shipping_zone_id'])) {
            return $this->unavailableEstimate(
                reason: 'missing_destination',
                destinationLabel: $destinationLabel,
                scope: $scope,
                method: $method,
            );
        }

        try {
            $context = $this->shippingCostService->resolveRateContext([
                'shipping_method_id' => $method->id,
                'shipping_zone_id' => $destination['shipping_zone_id'] ?? null,
                'state_id' => $destination['state_id'] ?? null,
                'lga_id' => $destination['lga_id'] ?? null,
                'subtotal' => (float) ($options['subtotal'] ?? ((float) $variant->regular_price * max((int) ($options['quantity'] ?? 1), 1))),
                'weight_kg' => (float) ($variant->weight ?? 0),
                'items' => [[
                    'variant_id' => (int) $variant->id,
                    'quantity' => max((int) ($options['quantity'] ?? 1), 1),
                    'weight' => (float) ($variant->weight ?? 0),
                ]],
            ]);
        } catch (ShippingRateNotFoundException) {
            return $this->unavailableEstimate(
                reason: 'timing_unavailable',
                destinationLabel: $destinationLabel,
                scope: $scope,
                method: $method,
            );
        }

        /** @var ShippingRate|null $rate */
        $rate = $context['rate'];
        $timing = $this->resolveTiming($method, $rate);

        if ($warehouse) {
            $adjustment = $this->warehouseTransitAdjustment($warehouse, $destination);
            $timing['transit_days_min'] = ($timing['transit_days_min'] ?? 0) + $adjustment;
            $timing['transit_days_max'] = ($timing['transit_days_max'] ?? ($timing['transit_days_min'] ?? 0)) + $adjustment;
        }

        $window = $this->computeWindow($timing, $method, $rate);
        if (!$window) {
            return $this->unavailableEstimate(
                reason: 'timing_unavailable',
                destinationLabel: $destinationLabel,
                scope: $scope,
                method: $method,
            );
        }

        return $this->buildEstimatePayload(
            method: $method,
            window: $window,
            destinationLabel: $destinationLabel,
            warehouse: $warehouse,
            scope: $scope,
        );
    }

    protected function buildEstimatePayload(
        ShippingMethod $method,
        array $window,
        ?string $destinationLabel,
        ?Warehouse $warehouse,
        string $scope,
    ): array {
        return [
            'available' => true,
            'reason' => null,
            'scope' => $scope,
            'destination_label' => $destinationLabel,
            'earliest_date' => $window['earliest_date'],
            'latest_date' => $window['latest_date'],
            'window' => $window,
            'method' => [
                'id' => (int) $method->id,
                'name' => $method->name,
                'type' => $method->method_type,
            ],
            'warehouse' => $warehouse ? [
                'id' => (int) $warehouse->id,
                'name' => $warehouse->name,
                'state_name' => $warehouse->state?->name,
            ] : null,
            'storefront_message' => $this->deliveryDateFormatter->buildStorefrontMessage($method->method_type, $destinationLabel, $window),
            'checkout_message' => $this->deliveryDateFormatter->buildCheckoutMessage($method->method_type, $window),
        ];
    }

    protected function unavailableEstimate(
        string $reason,
        ?string $destinationLabel = null,
        string $scope = 'storefront',
        ?ShippingMethod $method = null,
    ): array {
        $message = match ($reason) {
            'missing_destination' => $scope === 'checkout'
                ? 'Delivery estimate available after selecting your location'
                : 'Estimated delivery available at checkout',
            'missing_method' => $scope === 'checkout'
                ? 'Delivery estimate available after selecting a shipping method'
                : 'Estimated delivery available at checkout',
            'missing_pickup_location' => 'Delivery date will be shown after selecting your location',
            default => 'Delivery timeline unavailable for this location',
        };

        return [
            'available' => false,
            'reason' => $reason,
            'scope' => $scope,
            'destination_label' => $destinationLabel,
            'earliest_date' => null,
            'latest_date' => null,
            'window' => null,
            'method' => $method ? [
                'id' => (int) $method->id,
                'name' => $method->name,
                'type' => $method->method_type,
            ] : null,
            'warehouse' => null,
            'storefront_message' => $message,
            'checkout_message' => $message,
        ];
    }

    protected function resolveMethod(?int $shippingMethodId, string $scope): ?ShippingMethod
    {
        if ($shippingMethodId) {
            return $this->shippingCostService->resolveMethod($shippingMethodId);
        }

        if ($scope === 'checkout') {
            return null;
        }

        return $this->shippingCostService->listActiveMethods()
            ->first(fn (ShippingMethod $method) => strtolower((string) $method->method_type) === ShippingMethod::TYPE_DELIVERY);
    }

    protected function resolveTiming(ShippingMethod $method, ?ShippingRate $rate): array
    {
        $processingMin = $rate?->processing_days_min ?? $method->processing_days_min;
        $processingMax = $rate?->processing_days_max ?? $method->processing_days_max ?? $processingMin;
        $transitMin = $rate?->transit_days_min ?? $method->transit_days_min;
        $transitMax = $rate?->transit_days_max ?? $method->transit_days_max ?? $transitMin;

        return [
            'processing_days_min' => $processingMin !== null ? (int) $processingMin : null,
            'processing_days_max' => $processingMax !== null ? (int) $processingMax : null,
            'transit_days_min' => $transitMin !== null ? (int) $transitMin : null,
            'transit_days_max' => $transitMax !== null ? (int) $transitMax : null,
            'cutoff_time' => $rate?->cutoff_time ?? $method->cutoff_time,
            'business_days_only' => $rate?->business_days_only ?? $method->business_days_only,
            'supports_weekend_delivery' => $rate?->supports_weekend_delivery ?? $method->supports_weekend_delivery,
        ];
    }

    protected function hasTimingConfig(array $timing): bool
    {
        return collect([
            $timing['processing_days_min'] ?? null,
            $timing['processing_days_max'] ?? null,
            $timing['transit_days_min'] ?? null,
            $timing['transit_days_max'] ?? null,
        ])->filter(fn ($value) => $value !== null)->isNotEmpty();
    }

    protected function computeWindow(array $timing, ShippingMethod $method, ?ShippingRate $rate): ?array
    {
        if (!$this->hasTimingConfig($timing)) {
            return null;
        }

        $processingMin = max((int) ($timing['processing_days_min'] ?? 0), 0);
        $processingMax = max((int) ($timing['processing_days_max'] ?? $processingMin), $processingMin);
        $transitMin = max((int) ($timing['transit_days_min'] ?? 0), 0);
        $transitMax = max((int) ($timing['transit_days_max'] ?? $transitMin), $transitMin);

        $businessDaysOnly = (bool) ($timing['business_days_only'] ?? true);
        $supportsWeekendDelivery = (bool) ($timing['supports_weekend_delivery'] ?? false);
        $cutoffTime = $timing['cutoff_time'] ?? null;

        $start = CarbonImmutable::now(config('app.timezone', 'Africa/Lagos'));
        $cutoffApplies = $cutoffTime && $start->format('H:i:s') > $cutoffTime;

        if ($cutoffApplies) {
            $start = $this->advanceDays($start, 1, $businessDaysOnly, $supportsWeekendDelivery);
        }

        $earliest = $this->advanceDays($start, $processingMin + $transitMin, $businessDaysOnly, $supportsWeekendDelivery);
        $latest = $this->advanceDays($start, $processingMax + $transitMax, $businessDaysOnly, $supportsWeekendDelivery);

        return $this->deliveryDateFormatter->formatWindow($earliest, $latest);
    }

    protected function advanceDays(
        CarbonImmutable $date,
        int $days,
        bool $businessDaysOnly,
        bool $supportsWeekendDelivery,
    ): CarbonImmutable {
        if ($days <= 0) {
            return $this->normalizeStartDate($date, $businessDaysOnly, $supportsWeekendDelivery);
        }

        $current = $this->normalizeStartDate($date, $businessDaysOnly, $supportsWeekendDelivery);
        $remaining = $days;

        while ($remaining > 0) {
            $current = $current->addDay();

            if ($this->isWorkingDay($current, $businessDaysOnly, $supportsWeekendDelivery)) {
                $remaining--;
            }
        }

        return $current;
    }

    protected function normalizeStartDate(
        CarbonImmutable $date,
        bool $businessDaysOnly,
        bool $supportsWeekendDelivery,
    ): CarbonImmutable {
        $current = $date;

        while (!$this->isWorkingDay($current, $businessDaysOnly, $supportsWeekendDelivery)) {
            $current = $current->addDay();
        }

        return $current;
    }

    protected function isWorkingDay(
        CarbonImmutable $date,
        bool $businessDaysOnly,
        bool $supportsWeekendDelivery,
    ): bool {
        if (!$businessDaysOnly) {
            return true;
        }

        if ($date->isWeekday()) {
            return true;
        }

        return $supportsWeekendDelivery;
    }

    protected function destinationLabel(array $destination): ?string
    {
        return $destination['destination_label']
            ?? $destination['city_name']
            ?? $destination['state_name']
            ?? null;
    }

    protected function resolveBestWarehousesForVariants(array $variantIds, array $destination): array
    {
        $variantIds = collect($variantIds)->map(fn ($id) => (int) $id)->filter()->unique()->all();
        if (empty($variantIds)) {
            return [];
        }

        $destinationStateId = !empty($destination['state_id']) ? (int) $destination['state_id'] : null;
        $destinationLgaId = !empty($destination['lga_id']) ? (int) $destination['lga_id'] : null;
        $destinationZoneId = $destination['shipping_zone_id'] ?? ($destinationStateId ? $this->shippingCostService->resolveZoneForState($destinationStateId) : null);

        $rows = DB::table('stock_entries')
            ->join('warehouses', 'warehouses.id', '=', 'stock_entries.warehouse_id')
            ->whereIn('stock_entries.variant_id', $variantIds)
            ->whereNotNull('stock_entries.warehouse_id')
            ->where('warehouses.active', true)
            ->groupBy('stock_entries.variant_id', 'warehouses.id', 'warehouses.name', 'warehouses.state_id', 'warehouses.lga_id', 'warehouses.country_id')
            ->selectRaw("
                stock_entries.variant_id,
                warehouses.id as warehouse_id,
                warehouses.name,
                warehouses.state_id,
                warehouses.lga_id,
                warehouses.country_id,
                SUM(CASE WHEN stock_entries.type = 'stock_in' THEN stock_entries.quantity ELSE -stock_entries.quantity END) as ledger_stock
            ")
            ->havingRaw("SUM(CASE WHEN stock_entries.type = 'stock_in' THEN stock_entries.quantity ELSE -stock_entries.quantity END) > 0")
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $warehouseIds = $rows->pluck('warehouse_id')->map(fn ($id) => (int) $id)->unique()->all();
        $warehouses = Warehouse::query()
            ->with(['state:id,name', 'lga:id,name', 'country:id,name'])
            ->whereIn('id', $warehouseIds)
            ->get()
            ->keyBy('id');

        $best = [];
        foreach ($rows->groupBy('variant_id') as $variantId => $variantRows) {
            $selected = collect($variantRows)
                ->map(function ($row) use ($warehouses, $destinationStateId, $destinationLgaId, $destinationZoneId) {
                    $warehouse = $warehouses->get((int) $row->warehouse_id);
                    if (!$warehouse) {
                        return null;
                    }

                    $score = $this->warehouseMatchScore($warehouse, $destinationStateId, $destinationLgaId, $destinationZoneId);

                    return [
                        'warehouse' => $warehouse,
                        'score' => $score,
                        'ledger_stock' => (int) $row->ledger_stock,
                    ];
                })
                ->filter()
                ->sortByDesc(fn (array $candidate) => $candidate['score'] * 100000 + $candidate['ledger_stock'])
                ->first();

            if ($selected) {
                $best[(int) $variantId] = $selected['warehouse'];
            }
        }

        return $best;
    }

    protected function warehouseMatchScore(
        Warehouse $warehouse,
        ?int $destinationStateId,
        ?int $destinationLgaId,
        ?int $destinationZoneId,
    ): int {
        if ($destinationLgaId && $warehouse->lga_id && (int) $warehouse->lga_id === $destinationLgaId) {
            return 40;
        }

        if ($destinationStateId && $warehouse->state_id && (int) $warehouse->state_id === $destinationStateId) {
            return 30;
        }

        if ($destinationZoneId && $warehouse->state_id) {
            $warehouseZoneId = $this->shippingCostService->resolveZoneForState((int) $warehouse->state_id);
            if ($warehouseZoneId && (int) $warehouseZoneId === (int) $destinationZoneId) {
                return 20;
            }
        }

        return 10;
    }

    protected function warehouseTransitAdjustment(Warehouse $warehouse, array $destination): int
    {
        $destinationStateId = !empty($destination['state_id']) ? (int) $destination['state_id'] : null;
        $destinationLgaId = !empty($destination['lga_id']) ? (int) $destination['lga_id'] : null;
        $destinationZoneId = $destination['shipping_zone_id'] ?? ($destinationStateId ? $this->shippingCostService->resolveZoneForState($destinationStateId) : null);

        if ($destinationLgaId && $warehouse->lga_id && (int) $warehouse->lga_id === $destinationLgaId) {
            return 0;
        }

        if ($destinationStateId && $warehouse->state_id && (int) $warehouse->state_id === $destinationStateId) {
            return 0;
        }

        if ($destinationZoneId && $warehouse->state_id) {
            $warehouseZoneId = $this->shippingCostService->resolveZoneForState((int) $warehouse->state_id);
            if ($warehouseZoneId && (int) $warehouseZoneId === (int) $destinationZoneId) {
                return 1;
            }
        }

        return 2;
    }
}
