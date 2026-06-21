<?php
namespace App\Domain\Inventory\Alerts\Detectors;
use App\Domain\Inventory\Alerts\Contracts\InventoryDetector;
use App\Models\ProductVariant;

class NegativeStockDetector implements InventoryDetector
{
    public function detect(): iterable
    {
        return ProductVariant::query()
            ->eligibleForOperationalAlerts()
            ->where('product_variants.quantity', '<', 0)
            ->get();
    }
}
