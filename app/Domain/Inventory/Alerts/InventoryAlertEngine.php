<?php
namespace App\Domain\Inventory\Alerts;
use App\Events\InventoryAlertRaised;
use App\Models\InventoryAlert;
use App\Models\ProductVariant;

class InventoryAlertEngine
{
    public const STOCK_LEVEL_TYPES = [
        'low_stock',
        'out_of_stock',
        'overstock',
        'slow_moving',
    ];

    public function raise(
        string $type,
        string $severity,
        ProductVariant $variant,
        ?int $warehouseId,
        string $message,
        array $meta = []
    ): InventoryAlert {
        $alert = InventoryAlert::firstOrCreate(
            [
                'type' => $type,
                'variant_id' => $variant->id,
                'warehouse_id' => $warehouseId,
                'status' => 'open',
            ],
            [
                'severity' => $severity,
                'message' => $message,
                'meta' => $meta,
                'first_detected_at' => now(),
            ]
        );

        $alert->update([
            'severity' => $severity,
            'message' => $message,
            'meta' => $meta,
            'last_seen_at' => now(),
        ]);

        if ($alert->wasRecentlyCreated) {
            event(new InventoryAlertRaised($alert));
        }

        return $alert;
    }

    public function resolveStockLevelAlertsForVariant(
        ProductVariant $variant,
        string $reason,
        ?int $resolvedBy = null
    ): int {
        return InventoryAlert::query()
            ->where('variant_id', $variant->id)
            ->where('status', 'open')
            ->whereIn('type', self::STOCK_LEVEL_TYPES)
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $resolvedBy,
                'resolved_reason' => $reason,
            ]);
    }

    /**
     * Close open stock-level alerts that belong to dropshipping variants — they hold
     * no local stock, so low/out-of-stock conditions are meaningless for them.
     */
    public function resolveStockLevelAlertsForDropshippingVariants(?int $resolvedBy = null): int
    {
        return InventoryAlert::query()
            ->where('status', 'open')
            ->whereIn('type', self::STOCK_LEVEL_TYPES)
            ->whereHas('variant', fn ($query) => $query
                ->where('fulfillment_type', ProductVariant::FULFILLMENT_DROPSHIPPING))
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $resolvedBy,
                'resolved_reason' => 'Variant is fulfilled by dropshipping; no local stock is expected.',
            ]);
    }

    /**
     * Close "item not found during audit" alerts on variants an audit no longer
     * expects on the shelf: dropshipped ones, and paused or discontinued ones with
     * no stock on record. Counted mismatches stay open because a person recorded an
     * actual quantity for them.
     */
    public function resolveUnscannedAuditAlertsNotExpectedOnShelf(?int $variantId = null, ?int $resolvedBy = null): int
    {
        $unscanned = fn () => InventoryAlert::query()
            ->where('status', 'open')
            ->where('type', 'discrepancy')
            ->where('meta->missing_item', true)
            ->when($variantId, fn ($query) => $query->where('variant_id', $variantId));

        $resolve = fn (string $reason) => [
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy,
            'resolved_reason' => $reason,
        ];

        $dropshipped = $unscanned()
            ->whereHas('variant', fn ($query) => $query
                ->where('fulfillment_type', ProductVariant::FULFILLMENT_DROPSHIPPING))
            ->update($resolve('Variant is fulfilled by dropshipping; it is not stocked locally, so audits do not count it.'));

        $retired = $unscanned()
            ->whereHas('variant', fn ($query) => $query->whereNot(fn ($shelf) => $shelf->expectedOnShelf()))
            ->update($resolve('Variant is paused or discontinued with no stock on record, so audits do not expect it on the shelf.'));

        return $dropshipped + $retired;
    }

    public function resolveRecoveredOutOfStockAlerts(?int $resolvedBy = null): int
    {
        return InventoryAlert::query()
            ->where('type', 'out_of_stock')
            ->where('status', 'open')
            ->whereHas('variant', function ($query): void {
                $query
                    ->eligibleForStockLevelAlerts()
                    ->where('product_variants.available', '>', 0);
            })
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $resolvedBy,
                'resolved_reason' => 'Stock condition recovered.',
            ]);
    }

    public function resolveRecoveredLowStockAlerts(?int $resolvedBy = null): int
    {
        return InventoryAlert::query()
            ->where('type', 'low_stock')
            ->where('status', 'open')
            ->whereHas('variant', function ($query): void {
                $query
                    ->eligibleForStockLevelAlerts()
                    ->whereColumn('product_variants.available', '>', 'product_variants.reorder_point');
            })
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $resolvedBy,
                'resolved_reason' => 'Stock condition recovered.',
            ]);
    }

    /**
     * Immediately close recovered stock alerts for one variant. Called from the
     * variant observer after every stock movement, so restocks clear their alerts
     * on the spot instead of waiting for the next scheduled scan.
     */
    public function resolveRecoveredStockAlertsForVariant(ProductVariant $variant, ?int $resolvedBy = null): int
    {
        $available = (int) $variant->quantity - (int) ($variant->reserved ?? 0);

        if ($available <= 0) {
            return 0;
        }

        $types = ['out_of_stock'];

        if ($available > (int) ($variant->reorder_point ?? 0)) {
            $types[] = 'low_stock';
        }

        return InventoryAlert::query()
            ->where('variant_id', $variant->id)
            ->where('status', 'open')
            ->whereIn('type', $types)
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => $resolvedBy,
                'resolved_reason' => 'Stock condition recovered.',
            ]);
    }
}
