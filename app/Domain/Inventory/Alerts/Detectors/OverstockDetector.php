<?php

namespace App\Domain\Inventory\Alerts\Detectors;

use App\Domain\Inventory\Alerts\Contracts\InventoryDetector;
use App\Models\ProductVariant;
use App\Models\Setting;

class OverstockDetector implements InventoryDetector
{
    public function detect(): iterable
    {
        $multiplier = Setting::get('inventory.overstock_multiplier', 3);

        return ProductVariant::query()
            ->eligibleForStockLevelAlerts()
            ->where('product_variants.reorder_point', '>', 0)
            ->whereRaw('product_variants.available > product_variants.reorder_point * ?', [$multiplier])
            ->get();
    }
}
