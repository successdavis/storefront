<?php

namespace App\Console\Commands;

use App\Domain\Inventory\Alerts\Detectors\DiscrepancyDetector;
use App\Domain\Inventory\Alerts\Detectors\LowStockDetector;
use App\Domain\Inventory\Alerts\Detectors\NegativeStockDetector;
use App\Domain\Inventory\Alerts\Detectors\OutOfStockDetector;
use App\Domain\Inventory\Alerts\Detectors\OverstockDetector;
use App\Domain\Inventory\Alerts\Detectors\SlowMovingDetector;
use App\Domain\Inventory\Alerts\InventoryAlertEngine;
use App\Domain\Inventory\Alerts\InventoryAlertMailContext;
use App\Mail\InventoryAlertScanSummaryMail;
use App\Models\InventoryAlert;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class RunInventoryAlerts extends Command
{
    protected $signature = 'inventory:scan';

    public function handle(): int
    {
        $engine = app(InventoryAlertEngine::class);

        $alerts = InventoryAlertMailContext::withoutImmediateMail(function () use ($engine): Collection {
            $alerts = collect();

            foreach ((new LowStockDetector)->detect() as $variant) {
                $alerts->push($engine->raise(
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
                ));
            }

            foreach ((new OutOfStockDetector)->detect() as $variant) {
                $alerts->push($engine->raise(
                    'out_of_stock',
                    'critical',
                    $variant,
                    null,
                    "{$variant->label()} is out of stock"
                ));
            }

            $engine->resolveRecoveredOutOfStockAlerts();

            foreach ((new NegativeStockDetector)->detect() as $variant) {
                $alerts->push($engine->raise(
                    'negative_stock',
                    'critical',
                    $variant,
                    null,
                    "{$variant->label()} has negative stock"
                ));
            }

            foreach ((new OverstockDetector)->detect() as $variant) {
                $alerts->push($engine->raise(
                    'overstock',
                    'low',
                    $variant,
                    null,
                    "{$variant->label()} is overstocked"
                ));
            }

            $discrepancyThreshold = (int) Setting::get('inventory.discrepancy_high_threshold', 10);

            foreach ((new DiscrepancyDetector)->detect() as $variant) {
                $systemQuantity = (int) $variant->quantity;
                $ledgerQuantity = (int) $variant->ledger_quantity;
                $variance = $systemQuantity - $ledgerQuantity;

                $alerts->push($engine->raise(
                    'discrepancy',
                    abs($variance) >= $discrepancyThreshold ? 'high' : 'medium',
                    $variant,
                    null,
                    sprintf(
                        'Stock mismatch detected: %s (System: %d, Ledger: %d)',
                        $variant->label(),
                        $systemQuantity,
                        $ledgerQuantity,
                    ),
                    [
                        'system_quantity' => $systemQuantity,
                        'ledger_quantity' => $ledgerQuantity,
                        'variance' => $variance,
                    ]
                ));
            }

            $days = Setting::get('slow_moving_days', 7);

            foreach ((new SlowMovingDetector)->detect() as $variant) {
                $alerts->push($engine->raise(
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
                ));
            }

            return $alerts;
        });

        $this->sendScanSummary($alerts);

        return self::SUCCESS;
    }

    protected function sendScanSummary(Collection $alerts): void
    {
        $alerts = $alerts
            ->filter()
            ->unique(fn (InventoryAlert $alert): int => (int) $alert->id)
            ->filter(fn (InventoryAlert $alert): bool => $alert->wasRecentlyCreated)
            ->filter(fn (InventoryAlert $alert): bool => $this->shouldEmailAlert($alert))
            ->values();

        if ($alerts->isEmpty()) {
            return;
        }

        $recipient = Setting::get('admin_email') ?: Setting::get('business_email');

        if (! $recipient) {
            $this->warn('Inventory alert summary email skipped: no admin email is configured.');

            return;
        }

        $alerts = new EloquentCollection($alerts->all());
        $alerts->loadMissing([
            'variant.product',
            'variant.values',
            'variant.values.type',
        ]);

        Mail::to($recipient)->send(new InventoryAlertScanSummaryMail($alerts, now()->toDayDateTimeString()));
    }

    protected function shouldEmailAlert(InventoryAlert $alert): bool
    {
        if ($alert->status !== 'open' || $alert->suppressed_at) {
            return false;
        }

        return ! $alert->snoozed_until || $alert->snoozed_until->lte(now());
    }
}
