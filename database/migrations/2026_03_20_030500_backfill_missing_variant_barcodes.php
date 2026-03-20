<?php

use App\Domain\Inventory\Barcode\BarcodeService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(BarcodeService::class)->assignMissingBarcodes();
    }

    public function down(): void
    {
        // No-op: barcode backfill is intentionally irreversible.
    }
};
