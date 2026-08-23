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
        // Restocks (or released reservations) clear recovered stock alerts immediately,
        // instead of leaving them open until the next scheduled inventory scan.
        if (! $variant->wasChanged(['quantity', 'reserved'])) {
            return;
        }

        app(InventoryAlertEngine::class)->resolveRecoveredStockAlertsForVariant($variant);
    }
}
