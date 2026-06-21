<?php
namespace App\Domain\Inventory\Alerts\Detectors;
use App\Domain\Inventory\Alerts\Contracts\InventoryDetector;
use App\Models\ProductVariant;

class OutOfStockDetector implements InventoryDetector
{
    public function detect(): iterable
    {
        return ProductVariant::query()
            ->eligibleForStockLevelAlerts()
            ->where('product_variants.available', '<=', 0)
            ->get();
    }
}
