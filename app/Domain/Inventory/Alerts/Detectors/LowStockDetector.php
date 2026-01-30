<?php

namespace App\Domain\Inventory\Alerts\Detectors;

use App\Domain\Inventory\Alerts\Contracts\InventoryDetector;
use App\Models\ProductVariant;

class LowStockDetector implements InventoryDetector
{
    public function detect(): iterable
    {
        return ProductVariant::query()
            ->where('track_inventory', true)
            ->where('available', '>', 0)
            ->whereColumn('available', '<=', 'reorder_point')

            ->select([
                'product_variants.*',
            ])

            // how far below (or equal) threshold
            ->selectRaw('reorder_point - available as stock_deficit')

            ->get();
    }
}
