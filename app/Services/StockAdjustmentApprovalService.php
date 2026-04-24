<?php

namespace App\Services;

use App\Enums\StockAdjustmentType;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\StockAuditItem;
use App\Models\StockAuditItemLock;
use App\Models\StockAuditSession;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentApprovalService
{
    public const ACTION_APPROVE = 'approve';
    public const ACTION_REJECT = 'reject';

    public function __construct(
        protected InventoryService $inventoryService,
        protected AccountingService $accountingService,
    ) {}

    public function submit(array $data, int $submittedBy): StockAdjustment
    {
        return DB::transaction(function () use ($data, $submittedBy): StockAdjustment {
            $variant = ProductVariant::query()
                ->lockForUpdate()
                ->findOrFail((int) $data['variant_id']);

            $auditItem = null;
            if (!empty($data['stock_audit_item_id'])) {
                $auditItem = StockAuditItem::query()
                    ->with('session:id,status,warehouse_id')
                    ->lockForUpdate()
                    ->findOrFail((int) $data['stock_audit_item_id']);

                if ((int) $auditItem->variant_id !== (int) $variant->id) {
                    throw ValidationException::withMessages([
                        'variant_id' => 'The selected audit item does not belong to this variant.',
                    ]);
                }

                if ($auditItem->conflict_reason) {
                    throw ValidationException::withMessages([
                        'variant_id' => $auditItem->conflict_reason,
                    ]);
                }

                if ($auditItem->stock_adjustment_id) {
                    return StockAdjustment::query()->findOrFail((int) $auditItem->stock_adjustment_id);
                }
            }

            $adjustment = StockAdjustment::create([
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'variant_id' => $variant->id,
                'previous_quantity' => (int) $variant->quantity,
                'adjusted_quantity' => (int) $data['adjusted_quantity'],
                'adjustment_type' => $this->normalizeAdjustmentType(
                    $data['adjustment_type'] ?? StockAdjustmentType::CORRECTION->value,
                    (int) $data['adjusted_quantity'],
                )->value,
                'reason' => $data['reason'],
                'employee_id' => $submittedBy,
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'adjusted_at' => $data['adjusted_at'] ?? now(),
                'status' => StockAdjustment::STATUS_PENDING,
            ]);

            if ($auditItem) {
                $auditItem->update([
                    'stock_adjustment_id' => $adjustment->id,
                ]);
            }

            return $adjustment;
        });
    }

    public function approve(StockAdjustment $stockAdjustment, int $approverId, ?string $approvalNote = null): StockAdjustment
    {
        return DB::transaction(function () use ($stockAdjustment, $approverId, $approvalNote): StockAdjustment {
            $adjustment = StockAdjustment::query()
                ->lockForUpdate()
                ->findOrFail($stockAdjustment->id);

            if ($adjustment->status !== StockAdjustment::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending adjustments can be approved.',
                ]);
            }

            $effectiveType = $adjustment->adjustment_type instanceof StockAdjustmentType
                ? $adjustment->adjustment_type
                : StockAdjustmentType::tryFrom((string) $adjustment->adjustment_type)
                    ?? StockAdjustmentType::CORRECTION;

            if (array_key_exists('adjustment_type', $stockAdjustment->getAttributes())) {
                $adjustmentType = $stockAdjustment->adjustment_type instanceof StockAdjustmentType
                    ? $stockAdjustment->adjustment_type
                    : StockAdjustmentType::tryFrom((string) $stockAdjustment->adjustment_type);

                if ($adjustmentType && $effectiveType->value !== $adjustmentType->value) {
                    $adjustment->update([
                        'adjustment_type' => $this->normalizeAdjustmentType(
                            $adjustmentType->value,
                            (int) $adjustment->adjusted_quantity,
                        )->value,
                    ]);
                    $adjustment->refresh();
                    $effectiveType = $adjustment->adjustment_type;
                }
            }

            $this->normalizeAdjustmentType($effectiveType->value, (int) $adjustment->adjusted_quantity);

            $auditItem = $this->resolveAuditItemForAdjustment($adjustment);
            if ($auditItem) {
                $auditItem = StockAuditItem::query()
                    ->with('session:id,status,warehouse_id')
                    ->lockForUpdate()
                    ->findOrFail($auditItem->id);

                if ($auditItem->conflict_reason) {
                    throw ValidationException::withMessages([
                        'status' => $auditItem->conflict_reason,
                    ]);
                }

                $lock = StockAuditItemLock::query()
                    ->where('variant_id', $adjustment->variant_id)
                    ->where('warehouse_scope_key', $this->warehouseScopeKey($auditItem->session?->warehouse_id ?? $adjustment->warehouse_id))
                    ->lockForUpdate()
                    ->first();

                if (!$lock || (int) $lock->session_id !== (int) $auditItem->session_id) {
                    throw ValidationException::withMessages([
                        'status' => 'This audit discrepancy is no longer the active count for this stock bucket.',
                    ]);
                }
            }

            $variant = ProductVariant::query()->findOrFail($adjustment->variant_id);

            $baseData = [
                'warehouse_id' => $adjustment->warehouse_id,
                'variant_id' => $adjustment->variant_id,
                'reason' => $adjustment->reason,
                'employee_id' => $approverId,
                'note' => $approvalNote ?: $adjustment->note,
                'effective_at' => $adjustment->adjusted_at ?? now(),
                'source_type' => StockAdjustment::class,
                'source_id' => $adjustment->id,
            ];

            if ((int) $adjustment->adjusted_quantity > 0) {
                $this->inventoryService->stockIn([
                    ...$baseData,
                    'quantity' => (int) $adjustment->adjusted_quantity,
                    'unit_cost' => (float) ($variant->last_purchase_price ?? $variant->average_cost ?? 0),
                ]);
            } else {
                $this->inventoryService->stockOut([
                    ...$baseData,
                    'quantity' => abs((int) $adjustment->adjusted_quantity),
                ]);
            }

            $adjustment->update([
                'status' => StockAdjustment::STATUS_APPROVED,
                'approved_by' => $approverId,
                'approved_at' => now(),
                'approval_note' => $approvalNote,
            ]);

            $this->accountingService->postStockAdjustment($adjustment->fresh(), $approverId);

            if ($auditItem) {
                $this->completeAuditSessionIfResolved((int) $auditItem->session_id);
            }

            return $adjustment->fresh();
        });
    }

    public function reject(StockAdjustment $stockAdjustment, int $rejectedBy, ?string $approvalNote = null): StockAdjustment
    {
        return DB::transaction(function () use ($stockAdjustment, $rejectedBy, $approvalNote): StockAdjustment {
            $adjustment = StockAdjustment::query()
                ->lockForUpdate()
                ->findOrFail($stockAdjustment->id);

            if ($adjustment->status !== StockAdjustment::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending adjustments can be rejected.',
                ]);
            }

            $auditItem = $this->resolveAuditItemForAdjustment($adjustment);

            $adjustment->update([
                'status' => StockAdjustment::STATUS_REJECTED,
                'rejected_by' => $rejectedBy,
                'rejected_at' => now(),
                'approval_note' => $approvalNote,
            ]);

            if ($auditItem) {
                $this->completeAuditSessionIfResolved((int) $auditItem->session_id);
            }

            return $adjustment->fresh();
        });
    }

    public function bulkReview(array $adjustmentIds, string $action, int $actorId, ?string $approvalNote = null): array
    {
        if (!in_array($action, [self::ACTION_APPROVE, self::ACTION_REJECT], true)) {
            throw ValidationException::withMessages([
                'action' => 'This bulk action is not supported.',
            ]);
        }

        $success = 0;
        $failed = [];

        StockAdjustment::query()
            ->whereIn('id', $adjustmentIds)
            ->orderBy('id')
            ->get()
            ->each(function (StockAdjustment $adjustment) use ($action, $actorId, $approvalNote, &$success, &$failed): void {
                try {
                    if ($action === self::ACTION_APPROVE) {
                        $this->approve($adjustment, $actorId, $approvalNote);
                    } else {
                        $this->reject($adjustment, $actorId, $approvalNote);
                    }

                    $success++;
                } catch (\Throwable $exception) {
                    $failed[] = [
                        'adjustment_id' => (int) $adjustment->id,
                        'message' => $exception instanceof ValidationException
                            ? collect($exception->errors())->flatten()->first()
                            : $exception->getMessage(),
                    ];
                }
            });

        return [
            'success_count' => $success,
            'failed' => $failed,
        ];
    }

    protected function resolveAuditItemForAdjustment(StockAdjustment $adjustment): ?StockAuditItem
    {
        $linkedItem = StockAuditItem::query()
            ->where('stock_adjustment_id', $adjustment->id)
            ->first();

        if ($linkedItem) {
            return $linkedItem;
        }

        if ($adjustment->reason !== 'count_discrepancy' || !preg_match('/^AUDIT-(\d+)-(\d+)$/', (string) $adjustment->reference, $matches)) {
            return null;
        }

        return StockAuditItem::query()
            ->where('session_id', (int) $matches[1])
            ->where('variant_id', (int) $matches[2])
            ->first();
    }

    protected function completeAuditSessionIfResolved(int $sessionId): void
    {
        $session = StockAuditSession::query()
            ->lockForUpdate()
            ->find($sessionId);

        if (!$session) {
            return;
        }

        $items = StockAuditItem::query()
            ->with('stockAdjustment:id,status')
            ->where('session_id', $sessionId)
            ->get(['id', 'session_id', 'variance', 'stock_adjustment_id', 'conflict_reason']);

        $hasOutstandingConflicts = $items->contains(fn (StockAuditItem $item) => filled($item->conflict_reason));
        if ($hasOutstandingConflicts) {
            return;
        }

        $hasPendingAdjustments = $items->contains(function (StockAuditItem $item): bool {
            if ((int) $item->variance === 0) {
                return false;
            }

            return !$item->stockAdjustment
                || $item->stockAdjustment->status === StockAdjustment::STATUS_PENDING;
        });

        if ($hasPendingAdjustments) {
            return;
        }

        $session->update([
            'status' => StockAuditSession::STATUS_REVIEWED,
            'last_activity_at' => now(),
        ]);

        StockAuditItemLock::query()
            ->where('session_id', $sessionId)
            ->delete();
    }

    protected function warehouseScopeKey(?int $warehouseId): int
    {
        return $warehouseId ? (int) $warehouseId : 0;
    }

    protected function normalizeAdjustmentType(string $adjustmentType, int $adjustedQuantity): StockAdjustmentType
    {
        $type = StockAdjustmentType::tryFrom($adjustmentType);

        if (!$type) {
            throw ValidationException::withMessages([
                'adjustment_type' => 'The selected adjustment type is invalid.',
            ]);
        }

        if (!$type->allowsQuantityDelta($adjustedQuantity)) {
            throw ValidationException::withMessages([
                'adjustment_type' => match ($type) {
                    StockAdjustmentType::LOSS => 'Loss adjustments must reduce stock.',
                    StockAdjustmentType::GAIN => 'Gain adjustments must increase stock.',
                    StockAdjustmentType::CORRECTION => 'Correction adjustments require a non-zero quantity change.',
                },
            ]);
        }

        return $type;
    }
}
