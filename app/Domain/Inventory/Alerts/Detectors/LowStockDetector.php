<?php

namespace App\Domain\Inventory\Alerts\Detectors;

use App\Domain\Inventory\Alerts\Contracts\InventoryDetector;
use App\Models\ProductVariant;

class LowStockDetector implements InventoryDetector
{
    public function detect(): iterable
    {
        return ProductVariant::query()
            ->eligibleForStockLevelAlerts()
            ->where('product_variants.available', '>', 0)
            ->whereColumn('product_variants.available', '<=', 'product_variants.reorder_point')

            ->select([
                'product_variants.*',
            ])

            // how far below (or equal) threshold
            ->selectRaw('product_variants.reorder_point - product_variants.available as stock_deficit')

            ->get();
    }
}
