<?php

namespace App\Console\Commands;

use App\Domain\Inventory\Alerts\Detectors\LowStockDetector;
use App\Domain\Inventory\Alerts\Detectors\NegativeStockDetector;
use App\Domain\Inventory\Alerts\Detectors\OutOfStockDetector;
use App\Domain\Inventory\Alerts\Detectors\OverstockDetector;
use App\Domain\Inventory\Alerts\Detectors\SlowMovingDetector;
use App\Domain\Inventory\Alerts\InventoryAlertEngine;
use App\Models\Setting;
use Illuminate\Console\Command;

class RunInventoryAlerts extends Command
{
    protected $signature = 'inventory:scan';

    public function handle()
    {
        $engine = app(InventoryAlertEngine::class);

        foreach ((new LowStockDetector)->detect() as $variant) {
            $engine->raise(
                'low_stock',
                'high',
                $variant,
                null,
                sprintf(
                    'Low Stock: %s (%d units left)',
                    $variant->label(),
                    $variant->available,
                    $variant->reorder_point
                )
            );
        }

        foreach ((new OutOfStockDetector)->detect() as $variant) {
            $engine->raise(
                'out_of_stock',
                'critical',
                $variant,
                null,
                "{$variant->label()} is out of stock"
            );
        }

        foreach ((new NegativeStockDetector)->detect() as $variant) {
            $engine->raise(
                'negative_stock',
                'critical',
                $variant,
                null,
                "{$variant->label()} has negative stock"
            );
        }

        foreach ((new OverstockDetector)->detect() as $variant) {
            $engine->raise(
                'overstock',
                'low',
                $variant,
                null,
                "{$variant->label()} is overstocked"
            );
        }

        $days = Setting::get('slow_moving_days', 7);

        foreach ((new SlowMovingDetector)->detect() as $variant) {
            $engine->raise(
                'slow_moving',
                'low',
                $variant,
                null,
                sprintf(
                    '%s is slow moving: %d total sales, %d sales in last %d days',
                    $variant->label(),
                    $variant->lifetime_sales,
                    $variant->recent_sales,
                    $days
                )
            );
        }
    }
}


