<?php

namespace App\Domain\Inventory\Audit;

use App\Domain\Inventory\Alerts\InventoryAlertEngine;
use App\Domain\Inventory\Support\VariantNameFormatter;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\StockAuditItem;
use App\Models\StockAuditSession;
use App\Services\StockAdjustmentApprovalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAuditService
{
    public function __construct(
        protected InventoryAlertEngine $alertEngine,
        protected VariantNameFormatter $variantNameFormatter,
        protected StockAdjustmentApprovalService $stockAdjustmentApprovalService,
    ) {}

    public function auditRows(
        string $scopeType = StockAuditSession::SCOPE_FULL,
        ?int $categoryId = null,
    ): Collection
    {
        [$scopeType, $categoryId] = $this->normalizeScope($scopeType, $categoryId);

        return $this->scopedVariantQuery($scopeType, $categoryId)
            ->with([
                'product:id,name',
                'values:id,variant_type_id,value',
                'values.type:id,name',
            ])
            ->orderBy('id')
            ->get([
                'id',
                'product_id',
                'sku',
                'barcode',
                'quantity',
                'reserved',
                'track_inventory',
            ])
            ->map(function (ProductVariant $variant): array {
                return [
                    'id' => (int) $variant->id,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'track_inventory' => (bool) $variant->track_inventory,
                    'system_quantity' => (int) $variant->quantity,
                    'reserved' => (int) ($variant->reserved ?? 0),
                    'display_name' => $this->variantNameFormatter->format($variant),
                ];
            })
            ->values();
    }

    public function findByBarcode(
        string $barcode,
        ?StockAuditSession $session = null,
    ): ?array
    {
        $query = $session
            ? $this->scopedVariantQuery($session->scope_type, $session->category_id)
            : ProductVariant::query();

        $variant = $query
            ->with([
                'product:id,name',
                'values:id,variant_type_id,value',
                'values.type:id,name',
            ])
            ->where('barcode', trim($barcode))
            ->first([
                'id',
                'product_id',
                'sku',
                'barcode',
                'quantity',
                'reserved',
                'track_inventory',
            ]);

        if (!$variant) {
            return null;
        }

        return [
            'id' => (int) $variant->id,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'track_inventory' => (bool) $variant->track_inventory,
            'system_quantity' => (int) $variant->quantity,
            'reserved' => (int) ($variant->reserved ?? 0),
            'display_name' => $this->variantNameFormatter->format($variant),
        ];
    }

    public function findOrCreateInProgressSession(
        ?int $startedBy,
        string $scopeType = StockAuditSession::SCOPE_FULL,
        ?int $categoryId = null,
        ?int $warehouseId = null,
    ): StockAuditSession {
        [$scopeType, $categoryId] = $this->normalizeScope($scopeType, $categoryId);

        if ($startedBy) {
            $existing = StockAuditSession::query()
                ->where('status', StockAuditSession::STATUS_IN_PROGRESS)
                ->where('started_by', $startedBy)
                ->where('scope_type', $scopeType)
                ->where('category_id', $categoryId)
                ->where('warehouse_id', $warehouseId)
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $expectedItems = $this->expectedItemsCount($scopeType, $categoryId);

        return StockAuditSession::create([
            'warehouse_id' => $warehouseId,
            'scope_type' => $scopeType,
            'category_id' => $categoryId,
            'status' => StockAuditSession::STATUS_IN_PROGRESS,
            'total_expected_items' => $expectedItems,
            'total_scanned_items' => 0,
            'coverage_percentage' => 0,
            'started_by' => $startedBy,
            'started_at' => now(),
        ]);
    }

    public function sessionRows(StockAuditSession $session): Collection
    {
        $itemsByVariantId = $session->items()
            ->get(['variant_id', 'physical_quantity', 'variance'])
            ->keyBy('variant_id');

        return $this->auditRows($session->scope_type, $session->category_id)
            ->map(function (array $row) use ($itemsByVariantId): array {
                $item = $itemsByVariantId->get($row['id']);

                $row['has_been_audited'] = (bool) $item;
                $row['physical_quantity'] = $item ? (int) $item->physical_quantity : null;
                $row['variance'] = $item ? (int) $item->variance : null;

                return $row;
            })
            ->values();
    }

    public function sessionSummary(StockAuditSession $session): array
    {
        return [
            'id' => (int) $session->id,
            'status' => $session->status,
            'scope_type' => $session->scope_type,
            'category_id' => $session->category_id ? (int) $session->category_id : null,
            'warehouse_id' => $session->warehouse_id ? (int) $session->warehouse_id : null,
            'total_expected_items' => (int) $session->total_expected_items,
            'total_scanned_items' => (int) $session->total_scanned_items,
            'coverage_percentage' => (float) $session->coverage_percentage,
            'is_partial' => (bool) $session->is_partial,
            'started_at' => optional($session->started_at)->toDateTimeString(),
            'submitted_at' => optional($session->submitted_at)->toDateTimeString(),
        ];
    }

    public function getSession(int $sessionId): ?StockAuditSession
    {
        return StockAuditSession::query()
            ->with(['items'])
            ->find($sessionId);
    }

    public function upsertSessionItems(StockAuditSession $session, array $counts): array
    {
        $normalized = collect($counts)
            ->filter(fn ($line) => isset($line['variant_id'], $line['physical_quantity']))
            ->map(function (array $line): array {
                return [
                    'variant_id' => (int) $line['variant_id'],
                    'physical_quantity' => (int) $line['physical_quantity'],
                ];
            })
            ->keyBy('variant_id');

        if ($normalized->isEmpty()) {
            return [
                'processed' => 0,
                'scanned_items' => (int) $session->total_scanned_items,
                'coverage_percentage' => (float) $session->coverage_percentage,
            ];
        }

        $allowedVariantIds = $this->scopedVariantQuery($session->scope_type, $session->category_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $variants = ProductVariant::query()
            ->whereIn('id', $normalized->keys()->all())
            ->whereIn('id', $allowedVariantIds)
            ->lockForUpdate()
            ->get(['id', 'quantity'])
            ->keyBy('id');

        if ($variants->count() !== $normalized->count()) {
            throw ValidationException::withMessages([
                'counts' => 'One or more audit lines are outside the active audit scope.',
            ]);
        }

        foreach ($variants as $variant) {
            $physical = (int) $normalized->get((int) $variant->id)['physical_quantity'];
            $system = (int) $variant->quantity;
            $variance = $physical - $system;

            StockAuditItem::updateOrCreate(
                [
                    'session_id' => $session->id,
                    'variant_id' => $variant->id,
                ],
                [
                    'system_quantity' => $system,
                    'physical_quantity' => $physical,
                    'variance' => $variance,
                ],
            );
        }

        return $this->refreshSessionCoverage($session);
    }

    public function storeAudit(
        array $counts,
        ?int $warehouseId = null,
        ?int $employeeId = null,
        ?string $note = null,
        ?int $sessionId = null,
        string $scopeType = StockAuditSession::SCOPE_FULL,
        ?int $categoryId = null,
        bool $submitAnyway = false,
        string $source = 'audit',
    ): array {
        return DB::transaction(function () use (
            $counts,
            $warehouseId,
            $employeeId,
            $note,
            $sessionId,
            $scopeType,
            $categoryId,
            $submitAnyway,
            $source,
        ): array {
            if (!$employeeId) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Authenticated user is required for audit submission.',
                ]);
            }

            $session = $sessionId
                ? StockAuditSession::query()->lockForUpdate()->findOrFail($sessionId)
                : $this->findOrCreateInProgressSession(
                    startedBy: $employeeId,
                    scopeType: $scopeType,
                    categoryId: $categoryId,
                    warehouseId: $warehouseId,
                );

            if ($session->status !== StockAuditSession::STATUS_IN_PROGRESS) {
                throw ValidationException::withMessages([
                    'session_id' => 'Only in-progress audit sessions can be submitted.',
                ]);
            }

            if ($warehouseId && $session->warehouse_id !== $warehouseId) {
                $session->update(['warehouse_id' => $warehouseId]);
            }

            $upsertSummary = $this->upsertSessionItems($session, $counts);

            if ((int) $upsertSummary['processed'] === 0) {
                return [
                    'processed' => 0,
                    'matched' => 0,
                    'discrepancies' => [],
                    'alerts_raised' => 0,
                    'missing_count' => 0,
                    'session' => $this->sessionSummary($session->fresh()),
                ];
            }

            return $this->submitSession(
                session: $session,
                submittedBy: $employeeId,
                warehouseId: $warehouseId,
                note: $note,
                submitAnyway: $submitAnyway,
                source: $source,
            );
        });
    }

    protected function submitSession(
        StockAuditSession $session,
        int $submittedBy,
        ?int $warehouseId = null,
        ?string $note = null,
        bool $submitAnyway = false,
        string $source = 'audit',
    ): array {
        $session = StockAuditSession::query()
            ->lockForUpdate()
            ->findOrFail($session->id);

        $expectedVariantIds = $this->scopedVariantQuery($session->scope_type, $session->category_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $sessionItems = StockAuditItem::query()
            ->where('session_id', $session->id)
            ->with([
                'variant:id,product_id,sku,quantity,reserved,track_inventory',
                'variant.product:id,name',
                'variant.values:id,variant_type_id,value',
                'variant.values.type:id,name',
            ])
            ->get();

        $scannedVariantIds = $sessionItems->pluck('variant_id')->map(fn ($id) => (int) $id);
        $missingVariantIds = $expectedVariantIds->diff($scannedVariantIds)->values();
        $missingCount = $missingVariantIds->count();

        if ($missingCount > 0 && !$submitAnyway) {
            throw ValidationException::withMessages([
                'counts' => sprintf(
                    'You have not scanned %d items. Confirm submit-anyway to continue.',
                    $missingCount,
                ),
            ]);
        }

        $highVarianceThreshold = (int) Setting::get('inventory.discrepancy_high_threshold', 10);

        $matched = 0;
        $discrepancies = [];
        $alertsRaised = 0;

        foreach ($sessionItems as $item) {
            $variant = $item->variant;
            if (!$variant) {
                continue;
            }

            $systemQuantity = (int) $item->system_quantity;
            $physicalQuantity = (int) $item->physical_quantity;
            $variance = $physicalQuantity - $systemQuantity;

            if ($variance === 0) {
                $matched++;
                continue;
            }

            $adjustment = $this->stockAdjustmentApprovalService->submit([
                'warehouse_id' => $warehouseId ?? $session->warehouse_id,
                'variant_id' => $variant->id,
                'adjusted_quantity' => $variance,
                'reason' => 'count_discrepancy',
                'reference' => sprintf('AUDIT-%d-%d', $session->id, $variant->id),
                'note' => $note,
                'adjusted_at' => now(),
            ], $submittedBy);

            $displayName = $this->variantNameFormatter->format($variant);
            $isNegative = $systemQuantity < 0 || $physicalQuantity < 0;
            $isHighVariance = abs($variance) >= $highVarianceThreshold;

            $alertType = $isNegative ? 'negative_stock' : 'discrepancy';
            $severity = $isNegative ? 'critical' : ($isHighVariance ? 'high' : 'medium');

            $message = match (true) {
                $isNegative => "Negative stock detected on SKU {$variant->sku} - immediate investigation required",
                $isHighVariance => sprintf(
                    'Unusual variance: %s %+d units without supplier record',
                    $displayName,
                    $variance,
                ),
                default => sprintf(
                    'Stock mismatch detected: %s (System: %d, Physical: %d)',
                    $displayName,
                    $systemQuantity,
                    $physicalQuantity,
                ),
            };

            $meta = [
                'system_quantity' => $systemQuantity,
                'physical_quantity' => $physicalQuantity,
                'variance' => $variance,
                'stock_adjustment_id' => $adjustment->id,
                'audit_session_id' => $session->id,
                'source' => $source,
            ];

            $this->alertEngine->raise(
                $alertType,
                $severity,
                $variant,
                $warehouseId ?? $session->warehouse_id,
                $message,
                $meta,
            );

            $alertsRaised++;
            $discrepancies[] = [
                'variant_id' => (int) $variant->id,
                'adjustment_id' => (int) $adjustment->id,
                'system_quantity' => $systemQuantity,
                'physical_quantity' => $physicalQuantity,
                'variance' => $variance,
                'alert_type' => $alertType,
                'severity' => $severity,
                'message' => $message,
            ];
        }

        if ($missingCount > 0) {
            $missingVariants = ProductVariant::query()
                ->with([
                    'product:id,name',
                    'values:id,variant_type_id,value',
                    'values.type:id,name',
                ])
                ->whereIn('id', $missingVariantIds->all())
                ->get(['id', 'product_id', 'sku', 'quantity']);

            foreach ($missingVariants as $variant) {
                $message = sprintf(
                    'Item not found during audit: %s',
                    $this->variantNameFormatter->format($variant),
                );

                $this->alertEngine->raise(
                    'discrepancy',
                    'medium',
                    $variant,
                    $warehouseId ?? $session->warehouse_id,
                    $message,
                    [
                        'audit_session_id' => $session->id,
                        'source' => $source,
                        'unknown_state' => true,
                        'missing_item' => true,
                    ],
                );

                $alertsRaised++;
            }
        }

        $totalExpected = $expectedVariantIds->count();
        $totalScanned = $scannedVariantIds->count();
        $coverage = $totalExpected > 0
            ? round(($totalScanned / $totalExpected) * 100, 2)
            : 0;

        $session->update([
            'warehouse_id' => $warehouseId ?? $session->warehouse_id,
            'status' => StockAuditSession::STATUS_SUBMITTED,
            'total_expected_items' => $totalExpected,
            'total_scanned_items' => $totalScanned,
            'coverage_percentage' => $coverage,
            'is_partial' => $coverage < 100,
            'submitted_by' => $submittedBy,
            'submitted_at' => now(),
        ]);

        return [
            'processed' => $totalScanned,
            'matched' => $matched,
            'discrepancies' => $discrepancies,
            'alerts_raised' => $alertsRaised,
            'missing_count' => $missingCount,
            'session' => $this->sessionSummary($session->fresh()),
        ];
    }

    protected function refreshSessionCoverage(StockAuditSession $session): array
    {
        $session = StockAuditSession::query()
            ->lockForUpdate()
            ->findOrFail($session->id);

        $scannedItems = StockAuditItem::query()
            ->where('session_id', $session->id)
            ->count();

        $expectedItems = (int) $session->total_expected_items;
        if ($expectedItems <= 0) {
            $expectedItems = $this->expectedItemsCount($session->scope_type, $session->category_id);
        }

        $coverage = $expectedItems > 0
            ? round(($scannedItems / $expectedItems) * 100, 2)
            : 0;

        $session->update([
            'total_expected_items' => $expectedItems,
            'total_scanned_items' => $scannedItems,
            'coverage_percentage' => $coverage,
            'is_partial' => $session->status === StockAuditSession::STATUS_SUBMITTED
                ? $coverage < 100
                : false,
        ]);

        return [
            'processed' => $scannedItems,
            'scanned_items' => $scannedItems,
            'coverage_percentage' => $coverage,
            'session' => $this->sessionSummary($session->fresh()),
        ];
    }

    public function expectedItemsCount(
        string $scopeType = StockAuditSession::SCOPE_FULL,
        ?int $categoryId = null,
    ): int {
        [$scopeType, $categoryId] = $this->normalizeScope($scopeType, $categoryId);

        return (int) $this->scopedVariantQuery($scopeType, $categoryId)->count('id');
    }

    protected function scopedVariantQuery(
        string $scopeType = StockAuditSession::SCOPE_FULL,
        ?int $categoryId = null,
    ): Builder {
        [$scopeType, $categoryId] = $this->normalizeScope($scopeType, $categoryId);

        $query = ProductVariant::query();

        if ($scopeType === StockAuditSession::SCOPE_CATEGORY && $categoryId) {
            $query->whereHas('product.categories', function (Builder $builder) use ($categoryId): void {
                $builder->where('categories.id', $categoryId);
            });
        }

        return $query->orderBy('id');
    }

    protected function normalizeScope(string $scopeType, ?int $categoryId): array
    {
        $scopeType = in_array($scopeType, [StockAuditSession::SCOPE_FULL, StockAuditSession::SCOPE_CATEGORY], true)
            ? $scopeType
            : StockAuditSession::SCOPE_FULL;

        if ($scopeType !== StockAuditSession::SCOPE_CATEGORY) {
            $categoryId = null;
        }

        return [$scopeType, $categoryId];
    }
}
