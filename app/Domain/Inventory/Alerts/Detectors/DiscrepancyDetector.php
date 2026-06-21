<?php

namespace App\Domain\Inventory\Alerts\Detectors;

use App\Domain\Inventory\Alerts\Contracts\InventoryDetector;
use App\Models\ProductVariant;

class DiscrepancyDetector implements InventoryDetector
{
    public function detect(): iterable
    {
        return ProductVariant::query()
            ->leftJoin('stock_entries', 'stock_entries.variant_id', '=', 'product_variants.id')
            ->eligibleForOperationalAlerts()
            ->select('product_variants.*')
            ->selectRaw("COALESCE(SUM(CASE WHEN stock_entries.type = 'stock_in' THEN stock_entries.quantity WHEN stock_entries.type = 'stock_out' THEN -stock_entries.quantity ELSE 0 END), 0) as ledger_quantity")
            ->groupBy('product_variants.id')
            ->havingRaw('ledger_quantity <> product_variants.quantity')
            ->get();
    }
}
