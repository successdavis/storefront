<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentApprovalService
{
    public function __construct(
        protected InventoryService $inventoryService,
    ) {}

    public function submit(array $data, int $submittedBy): StockAdjustment
    {
        return DB::transaction(function () use ($data, $submittedBy): StockAdjustment {
            $variant = ProductVariant::query()
                ->lockForUpdate()
                ->findOrFail((int) $data['variant_id']);

            return StockAdjustment::create([
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'variant_id' => $variant->id,
                'previous_quantity' => (int) $variant->quantity,
                'adjusted_quantity' => (int) $data['adjusted_quantity'],
                'reason' => $data['reason'],
                'employee_id' => $submittedBy,
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'adjusted_at' => $data['adjusted_at'] ?? now(),
                'status' => StockAdjustment::STATUS_PENDING,
            ]);
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

            $adjustment->update([
                'status' => StockAdjustment::STATUS_REJECTED,
                'rejected_by' => $rejectedBy,
                'rejected_at' => now(),
                'approval_note' => $approvalNote,
            ]);

            return $adjustment->fresh();
        });
    }
}
