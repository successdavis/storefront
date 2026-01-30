<?php
namespace App\Domain\Inventory\Alerts;
use App\Events\InventoryAlertRaised;
use App\Models\InventoryAlert;
use App\Models\ProductVariant;

class InventoryAlertEngine
{
    public function raise(
        string $type,
        string $severity,
        ProductVariant $variant,
        ?int $warehouseId,
        string $message,
        array $meta = []
    ) {
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

        $alert->update(['last_seen_at' => now()]);

        if ($alert->wasRecentlyCreated) {
            event(new InventoryAlertRaised($alert));
        }
    }
}
