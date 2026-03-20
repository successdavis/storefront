<?php

namespace App\Observers;

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
}
