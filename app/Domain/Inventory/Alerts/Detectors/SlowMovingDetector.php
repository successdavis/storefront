<?php

namespace App\Domain\Inventory\Alerts\Detectors;

use App\Domain\Inventory\Alerts\Contracts\InventoryDetector;
use App\Models\ProductVariant;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SlowMovingDetector implements InventoryDetector
{
    public function detect(): iterable
    {
        $minAgeDays   = Setting::get('slow_moving_min_age', 30);
        $noSaleDays   = Setting::get('slow_moving_days', 7);
        $maxSales     = Setting::get('slow_moving_sales_threshold', 5);

        $ageCutoff    = Carbon::now()->subDays($minAgeDays);
        $recentCutoff = Carbon::now()->subDays($noSaleDays);

        return ProductVariant::query()
            ->where('track_inventory', true)
            ->where('available', '>', 0)
            ->where('created_at', '<=', $ageCutoff)

            ->select('product_variants.*')

            // lifetime sales
            ->selectRaw("
                (
                    SELECT COALESCE(SUM(oi.quantity), 0)
                    FROM order_items oi
                    JOIN orders o ON o.id = oi.order_id
                    WHERE oi.variant_id = product_variants.id
                    AND o.status != 'cancelled'
                ) as lifetime_sales
            ")

            // sales in last N days
            ->selectRaw("
                (
                    SELECT COALESCE(SUM(oi.quantity), 0)
                    FROM order_items oi
                    JOIN orders o ON o.id = oi.order_id
                    WHERE oi.variant_id = product_variants.id
                    AND o.status != 'cancelled'
                    AND o.created_at >= ?
                ) as recent_sales
            ", [$recentCutoff])

            // apply business rules
            ->having('lifetime_sales', '<=', $maxSales)
            ->having('recent_sales', '=', 0)

            ->get();
    }
}


