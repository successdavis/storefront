<?php

namespace App\Domain\Inventory\Audit;

use App\Domain\Inventory\Alerts\InventoryAlertEngine;
use App\Domain\Inventory\Support\VariantNameFormatter;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\StockAuditItem;
use App\Models\StockAuditItemLock;
use App\Models\StockAuditSession;
use App\Services\StockAdjustmentApprovalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
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

        $sessionItem = null;
        if ($session) {
            $sessionItem = $session->relationLoaded('items')
                ? $session->items->firstWhere('variant_id', $variant->id)
                : StockAuditItem::query()
                    ->where('session_id', $session->id)
                    ->where('variant_id', $variant->id)
                    ->first();
        }

        return [
            'id' => (int) $variant->id,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'track_inventory' => (bool) $variant->track_inventory,
            'system_quantity' => (int) $variant->quantity,
            'physical_quantity' => $sessionItem ? (int) $sessionItem->physical_quantity : null,
            'has_been_audited' => (bool) $sessionItem,
            'conflict_reason' => $sessionItem?->conflict_reason,
            'reserved' => (int) ($variant->reserved ?? 0),
            'display_name' => $this->variantNameFormatter->format($variant),
            ...$this->lockMetaForVariant($variant->id, $session),
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
                return $this->touchSessionActivity($existing);
            }
        }

        $expectedItems = $this->expectedItemsCount($scopeType, $categoryId, $warehouseId);

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
            'last_activity_at' => now(),
        ]);
    }

    public function sessionRows(StockAuditSession $session): Collection
    {
        $itemsByVariantId = $session->items()
            ->get(['variant_id', 'physical_quantity', 'variance', 'conflict_reason', 'conflicted_with_session_id'])
            ->keyBy('variant_id');

        return $this->auditRows($session->scope_type, $session->category_id)
            ->map(function (array $row) use ($itemsByVariantId, $session): array {
                $item = $itemsByVariantId->get($row['id']);

                $row['has_been_audited'] = (bool) $item;
                $row['physical_quantity'] = $item ? (int) $item->physical_quantity : null;
                $row['variance'] = $item ? (int) $item->variance : null;
                $row['conflict_reason'] = $item?->conflict_reason;

                return array_merge($row, $this->lockMetaForVariant($row['id'], $session));
            })
            ->values();
    }

    protected function lockMetaForVariant(int $variantId, ?StockAuditSession $session = null): array
    {
        $warehouseScopeKey = $this->warehouseScopeKey($session?->warehouse_id);

        $lock = StockAuditItemLock::query()
            ->with([
                'session.category:id,name',
            ])
            ->where('variant_id', $variantId)
            ->where('warehouse_scope_key', $warehouseScopeKey)
            ->first();

        if (!$lock || ($session && (int) $lock->session_id === (int) $session->id)) {
            return [
                'locked_by_other_session' => false,
                'lock_message' => null,
                'locked_session_id' => null,
            ];
        }

        $scopeLabel = $lock->session?->scope_type === StockAuditSession::SCOPE_CATEGORY
            ? sprintf('category audit (%s)', $lock->session?->category?->name ?? 'Unknown category')
            : 'full inventory audit';

        return [
            'locked_by_other_session' => true,
            'lock_message' => sprintf(
                'Already counted in session #%d for the same warehouse scope via %s.',
                (int) $lock->session_id,
                $scopeLabel,
            ),
            'locked_session_id' => (int) $lock->session_id,
        ];
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
            'last_activity_at' => optional($session->last_activity_at)->toDateTimeString(),
        ];
    }

    public function getSession(int $sessionId): ?StockAuditSession
    {
        return StockAuditSession::query()
            ->with(['items', 'category:id,name', 'warehouse:id,name', 'starter:id,name'])
            ->find($sessionId);
    }

    public function touchSessionActivity(StockAuditSession $session): StockAuditSession
    {
        $session->update([
            'last_activity_at' => now(),
        ]);

        return $session->fresh();
    }

    public function resumableSessions(
        ?int $startedBy = null,
        ?int $excludeSessionId = null,
    ): Collection {
        return StockAuditSession::query()
            ->with([
                'category:id,name',
                'warehouse:id,name',
                'starter:id,name',
            ])
            ->where('status', StockAuditSession::STATUS_IN_PROGRESS)
            ->when($startedBy, fn (Builder $query) => $query->where('started_by', $startedBy))
            ->when($excludeSessionId, fn (Builder $query) => $query->where('id', '!=', $excludeSessionId))
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (StockAuditSession $session): array {
                return [
                    'id' => (int) $session->id,
                    'reference' => sprintf('AUD-%06d', $session->id),
                    'scope_type' => $session->scope_type,
                    'category_id' => $session->category_id ? (int) $session->category_id : null,
                    'category_name' => $session->category?->name,
                    'warehouse_id' => $session->warehouse_id ? (int) $session->warehouse_id : null,
                    'warehouse_name' => $session->warehouse?->name,
                    'started_by' => $session->started_by ? (int) $session->started_by : null,
                    'started_by_name' => $session->starter?->name,
                    'started_at' => optional($session->started_at)->toDateTimeString(),
                    'last_activity_at' => optional($session->last_activity_at)->toDateTimeString(),
                    'total_expected_items' => (int) $session->total_expected_items,
                    'total_scanned_items' => (int) $session->total_scanned_items,
                    'coverage_percentage' => (float) $session->coverage_percentage,
                ];
            })
            ->values();
    }

    public function discardSession(StockAuditSession $session, ?int $actorId = null): void
    {
        if ($session->status !== StockAuditSession::STATUS_IN_PROGRESS) {
            throw ValidationException::withMessages([
                'session' => 'Only in-progress sessions can be discarded.',
            ]);
        }

        if ($actorId && $session->started_by && (int) $session->started_by !== $actorId) {
            throw ValidationException::withMessages([
                'session' => 'You are not allowed to discard this session.',
            ]);
        }

        $session->delete();
    }

    public function upsertSessionItems(StockAuditSession $session, array $counts): array
    {
        return DB::transaction(function () use ($session, $counts): array {
            $session = StockAuditSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            $normalized = collect($counts)
                ->values()
                ->filter(fn ($line) => isset($line['variant_id'], $line['physical_quantity']))
                ->map(function (array $line, int $index): array {
                    return [
                        'variant_id' => (int) $line['variant_id'],
                        'physical_quantity' => (int) $line['physical_quantity'],
                        'index' => $index,
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

            $warehouseScopeKey = $this->warehouseScopeKey($session->warehouse_id);
            $existingLocks = StockAuditItemLock::query()
                ->whereIn('variant_id', $normalized->keys()->all())
                ->where('warehouse_scope_key', $warehouseScopeKey)
                ->lockForUpdate()
                ->get()
                ->keyBy('variant_id');

            $conflicts = [];

            foreach ($variants as $variant) {
                $line = $normalized->get((int) $variant->id);
                $existingLock = $existingLocks->get((int) $variant->id);

                if ($existingLock && (int) $existingLock->session_id !== (int) $session->id) {
                    $conflictingSession = StockAuditSession::query()
                        ->with('category:id,name')
                        ->find($existingLock->session_id);

                    $scopeLabel = $conflictingSession?->scope_type === StockAuditSession::SCOPE_CATEGORY
                        ? sprintf('category audit (%s)', $conflictingSession?->category?->name ?? 'Unknown category')
                        : 'full inventory audit';

                    $conflicts["counts.{$line['index']}.variant_id"] = sprintf(
                        'This item is already counted in session #%d for the same warehouse scope via %s.',
                        (int) $existingLock->session_id,
                        $scopeLabel,
                    );
                    continue;
                }

                if (!$existingLock) {
                    try {
                        $lock = StockAuditItemLock::create([
                            'session_id' => $session->id,
                            'variant_id' => $variant->id,
                            'warehouse_id' => $session->warehouse_id,
                            'warehouse_scope_key' => $warehouseScopeKey,
                        ]);
                    } catch (QueryException) {
                        $conflicts["counts.{$line['index']}.variant_id"] = 'This item has just been counted in another audit session. Refresh and try again.';
                        continue;
                    }

                    $existingLocks->put((int) $variant->id, $lock);
                }

                $physical = (int) $line['physical_quantity'];
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
                        'conflict_reason' => null,
                        'conflicted_with_session_id' => null,
                    ],
                );
            }

            if (!empty($conflicts)) {
                throw ValidationException::withMessages($conflicts);
            }

            return $this->refreshSessionCoverage($session);
        });
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

            if ($session->items()->exists() && (int) ($session->warehouse_id ?? 0) !== (int) ($warehouseId ?? 0)) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Warehouse scope cannot be changed after counting has started for this audit session.',
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

        $expectedVariantIds = $this->sessionScopedVariantIds($session);

        $sessionItems = StockAuditItem::query()
            ->where('session_id', $session->id)
            ->with([
                'variant:id,product_id,sku,quantity,reserved,track_inventory',
                'variant.product:id,name',
                'variant.values:id,variant_type_id,value',
                'variant.values.type:id,name',
                'stockAdjustment:id,status',
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

            if ($item->conflict_reason) {
                $discrepancies[] = [
                    'variant_id' => (int) $variant->id,
                    'adjustment_id' => null,
                    'system_quantity' => $systemQuantity,
                    'physical_quantity' => $physicalQuantity,
                    'variance' => $variance,
                    'alert_type' => 'conflict',
                    'severity' => 'high',
                    'message' => $item->conflict_reason,
                ];
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
                'stock_audit_item_id' => $item->id,
            ], $submittedBy);

            $item->update([
                'stock_adjustment_id' => $adjustment->id,
            ]);

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
            'status' => empty($discrepancies) ? StockAuditSession::STATUS_REVIEWED : StockAuditSession::STATUS_SUBMITTED,
            'total_expected_items' => $totalExpected,
            'total_scanned_items' => $totalScanned,
            'coverage_percentage' => $coverage,
            'is_partial' => $coverage < 100,
            'submitted_by' => $submittedBy,
            'submitted_at' => now(),
            'last_activity_at' => now(),
        ]);

        if (empty($discrepancies)) {
            $this->releaseSessionLocks($session->id);
        }

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

        $expectedItems = $this->expectedItemsCount(
            $session->scope_type,
            $session->category_id,
            $session->warehouse_id,
            $session->id,
        );

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
            'last_activity_at' => now(),
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
        ?int $warehouseId = null,
        ?int $currentSessionId = null,
    ): int {
        [$scopeType, $categoryId] = $this->normalizeScope($scopeType, $categoryId);

        return (int) $this->scopedVariantQuery($scopeType, $categoryId)
            ->when(
                true,
                fn (Builder $query) => $this->excludeVariantsLockedByOtherSessions(
                    $query,
                    $warehouseId,
                    $currentSessionId,
                )
            )
            ->count('id');
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

    protected function warehouseScopeKey(?int $warehouseId): int
    {
        return $warehouseId ? (int) $warehouseId : 0;
    }

    protected function releaseSessionLocks(int $sessionId): void
    {
        StockAuditItemLock::query()
            ->where('session_id', $sessionId)
            ->delete();
    }

    protected function sessionScopedVariantIds(StockAuditSession $session): Collection
    {
        return $this->excludeVariantsLockedByOtherSessions(
            $this->scopedVariantQuery($session->scope_type, $session->category_id),
            $session->warehouse_id,
            $session->id,
        )
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    protected function excludeVariantsLockedByOtherSessions(
        Builder $query,
        ?int $warehouseId = null,
        ?int $currentSessionId = null,
    ): Builder {
        $warehouseScopeKey = $this->warehouseScopeKey($warehouseId);

        return $query->whereNotExists(function ($subQuery) use ($warehouseScopeKey, $currentSessionId): void {
            $subQuery->select(DB::raw(1))
                ->from('stock_audit_item_locks')
                ->whereColumn('stock_audit_item_locks.variant_id', 'product_variants.id')
                ->where('stock_audit_item_locks.warehouse_scope_key', $warehouseScopeKey);

            if ($currentSessionId) {
                $subQuery->where('stock_audit_item_locks.session_id', '!=', $currentSessionId);
            }
        });
    }
}
