<?php

namespace App\Observers;

use App\Domain\Inventory\Alerts\InventoryAlertEngine;
use App\Domain\Inventory\Barcode\BarcodeService;
use App\Models\ProductVariant;

class ProductVariantObserver
{
    public function __construct(
        protected BarcodeService $barcodeService,
    ) {}

    public function creating(ProductVariant $variant): void
    {
        $this->barcodeService->assignToVariant($variant);
    }

    public function created(ProductVariant $variant): void
    {
        if (filled($variant->barcode)) {
            return;
        }

        $this->barcodeService->assignToVariant($variant);
    }

    public function saved(ProductVariant $variant): void
    {
        // Once a variant is no longer expected on the shelf (dropshipped, or paused or
        // discontinued with nothing left), an audit's "not scanned" alert no longer applies.
        if (
            $variant->wasChanged(['fulfillment_type', 'replenishment_status', 'quantity'])
            && ! $variant->isExpectedOnShelf()
        ) {
            app(InventoryAlertEngine::class)->resolveUnscannedAuditAlertsNotExpectedOnShelf($variant->id);
        }

        // Restocks (or released reservations) clear recovered stock alerts immediately,
        // instead of leaving them open until the next scheduled inventory scan.
        if ($variant->wasChanged(['quantity', 'reserved'])) {
            app(InventoryAlertEngine::class)->resolveRecoveredStockAlertsForVariant($variant);
        }
    }
}
