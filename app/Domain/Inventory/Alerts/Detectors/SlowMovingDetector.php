<?php

namespace App\Domain\Inventory\Alerts\Detectors;

use App\Domain\Inventory\Alerts\Contracts\InventoryDetector;
use App\Models\ProductVariant;
use App\Models\Setting;
use Carbon\Carbon;

class SlowMovingDetector implements InventoryDetector
{
    public function detect(): iterable
    {
        $minAgeDays   = Setting::get('slow_moving_min_age', 30);
        $noSaleDays   = Setting::get('slow_moving_days', 7);
        $maxSales     = Setting::get('slow_moving_sales_threshold', 5);

        $ageCutoff    = Carbon::now()->subDays($minAgeDays);
        $recentCutoff = Carbon::now()->subDays($noSaleDays);
        $lifetimeSalesSql = "
            SELECT COALESCE(SUM(oi.quantity), 0)
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE oi.variant_id = product_variants.id
            AND o.status != 'cancelled'
        ";
        $recentSalesSql = "
            SELECT COALESCE(SUM(oi.quantity), 0)
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE oi.variant_id = product_variants.id
            AND o.status != 'cancelled'
            AND o.created_at >= ?
        ";

        return ProductVariant::query()
            ->eligibleForStockLevelAlerts()
            ->where('product_variants.available', '>', 0)
            ->where('product_variants.created_at', '<=', $ageCutoff)

            ->select('product_variants.*')

            // lifetime sales
            ->selectRaw("({$lifetimeSalesSql}) as lifetime_sales")

            // sales in last N days
            ->selectRaw("({$recentSalesSql}) as recent_sales", [$recentCutoff])

            // apply business rules
            ->whereRaw("({$lifetimeSalesSql}) <= ?", [$maxSales])
            ->whereRaw("({$recentSalesSql}) = 0", [$recentCutoff])

            ->get();
    }
}
