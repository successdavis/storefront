<?php
namespace App\Domain\Inventory\Alerts;
use App\Events\InventoryAlertRaised;
use App\Models\InventoryAlert;
use App\Models\ProductVariant;

class InventoryAlertEngine
{
    public const STOCK_LEVEL_TYPES = [
        'low_stock',
        'out_of_stock',
        'overstock',
        'slow_moving',
    ];

    public function raise(
        string $type,
        string $severity,
        ProductVariant $variant,
        ?int $warehouseId,
        string $message,
        array $meta = []
    ): InventoryAlert {
        $alert = InventoryAlert::firstOrCreate(
            [
                'type' => $type,
                'variant_id' => $variant->id,
                'warehouse_id' => $warehouseId,
                'status' => 'open',
            ],
            [
                'severity' => $severity,
                'message' => $message,
                'meta' => $meta,
                'first_detected_at' => now(),
            ]
        );

        $alert->update([
            'severity' => $severity,
            'message' => $message,
            'meta' => $meta,
            'last_seen_at' => now(),
        ]);

        if ($alert->wasRecentlyCreated) {
            event(new InventoryAlertRaised($alert));
        }

        return $alert;
    }

    public function resolveStockLevelAlertsForVariant(
        ProductVariant $variant,
        string $reason,
        ?int $resolvedBy = null
    ): int {
        return InventoryAlert::query()
            ->where('variant_id', $variant->id)
            ->where('status', 'open')
            ->whereIn('type', self::STOCK_LEVEL_TYPES)
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $resolvedBy,
                'resolved_reason' => $reason,
            ]);
    }

    public function resolveRecoveredOutOfStockAlerts(?int $resolvedBy = null): int
    {
        return InventoryAlert::query()
            ->where('type', 'out_of_stock')
            ->where('status', 'open')
            ->whereHas('variant', function ($query): void {
                $query
                    ->eligibleForStockLevelAlerts()
                    ->where('product_variants.available', '>', 0);
            })
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $resolvedBy,
                'resolved_reason' => 'Stock condition recovered.',
            ]);
    }
}
