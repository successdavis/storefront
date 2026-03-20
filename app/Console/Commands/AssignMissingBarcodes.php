<?php

namespace App\Console\Commands;

use App\Domain\Inventory\Barcode\BarcodeService;
use App\Models\ProductVariant;
use Illuminate\Console\Command;

class AssignMissingBarcodes extends Command
{
    protected $signature = 'inventory:barcodes:assign-missing';

    protected $description = 'Assign barcodes to product variants where barcode is null';

    public function handle(BarcodeService $barcodeService): int
    {
        $pending = ProductVariant::withTrashed()
            ->whereNull('barcode')
            ->count();

        if ($pending === 0) {
            $this->info('All variants already have barcodes.');

            return self::SUCCESS;
        }

        $barcodeService->assignMissingBarcodes();

        $remaining = ProductVariant::withTrashed()
            ->whereNull('barcode')
            ->count();

        $assigned = $pending - $remaining;

        $this->info("Assigned {$assigned} barcode(s).");

        if ($remaining > 0) {
            $this->warn("{$remaining} variant(s) still missing barcodes.");
        }

        return self::SUCCESS;
    }
}
