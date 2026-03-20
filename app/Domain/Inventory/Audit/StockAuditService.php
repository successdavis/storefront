<?php

namespace App\Domain\Inventory\Audit;

use App\Domain\Inventory\Alerts\InventoryAlertEngine;
use App\Domain\Inventory\Support\VariantNameFormatter;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\StockAdjustment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockAuditService
{
    public function __construct(
        protected InventoryAlertEngine $alertEngine,
        protected VariantNameFormatter $variantNameFormatter,
    ) {}

    public function auditRows(): Collection
    {
        return ProductVariant::query()
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

    public function findByBarcode(string $barcode): ?array
    {
        $variant = ProductVariant::query()
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

    public function storeAudit(
        array $counts,
        ?int $warehouseId = null,
        ?int $employeeId = null,
        ?string $note = null,
    ): array {
        return DB::transaction(function () use ($counts, $warehouseId, $employeeId, $note): array {
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
                    'matched' => 0,
                    'discrepancies' => [],
                    'alerts_raised' => 0,
                ];
            }

            $highVarianceThreshold = (int) Setting::get('inventory.discrepancy_high_threshold', 10);

            $variants = ProductVariant::query()
                ->with([
                    'product:id,name',
                    'values:id,variant_type_id,value',
                    'values.type:id,name',
                ])
                ->whereIn('id', $normalized->keys()->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $matched = 0;
            $discrepancies = [];

            foreach ($normalized as $variantId => $line) {
                /** @var ProductVariant|null $variant */
                $variant = $variants->get($variantId);
                if (!$variant) {
                    continue;
                }

                $systemQuantity = (int) $variant->quantity;
                $physicalQuantity = (int) $line['physical_quantity'];
                $variance = $physicalQuantity - $systemQuantity;

                if ($variance === 0) {
                    $matched++;
                    continue;
                }

                $adjustment = StockAdjustment::create([
                    'warehouse_id' => $warehouseId,
                    'variant_id' => $variant->id,
                    'previous_quantity' => $systemQuantity,
                    'adjusted_quantity' => $variance,
                    'reason' => 'count_discrepancy',
                    'employee_id' => $employeeId,
                    'reference' => sprintf('AUDIT-%s-%d', now()->format('YmdHis'), $variant->id),
                    'note' => $note,
                    'adjusted_at' => now(),
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
                ];

                $this->alertEngine->raise(
                    $alertType,
                    $severity,
                    $variant,
                    $warehouseId,
                    $message,
                    $meta,
                );

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

            return [
                'processed' => $normalized->count(),
                'matched' => $matched,
                'discrepancies' => $discrepancies,
                'alerts_raised' => count($discrepancies),
            ];
        });
    }
}
