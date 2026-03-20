<?php

namespace App\Domain\Inventory\Barcode;

use App\Models\ProductVariant;
use RuntimeException;

class BarcodeService
{
    public function generate(): string
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $base = $this->generateBaseDigits();
            $candidate = $base . $this->computeCheckDigit($base);

            if (!$this->barcodeExists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to generate a unique barcode after multiple attempts.');
    }

    public function assignToVariant(ProductVariant $variant): void
    {
        if (filled($variant->barcode)) {
            return;
        }

        $variant->barcode = $this->generate();

        if ($variant->exists) {
            $variant->saveQuietly();
        }
    }

    public function assignMissingBarcodes(): void
    {
        ProductVariant::withTrashed()
            ->whereNull('barcode')
            ->orderBy('id')
            ->chunkById(200, function ($variants): void {
                foreach ($variants as $variant) {
                    $this->assignToVariant($variant);
                }
            });
    }

    protected function generateBaseDigits(): string
    {
        $datePart = now()->format('ymd');
        $randomPart = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return $datePart . $randomPart;
    }

    protected function computeCheckDigit(string $baseDigits): int
    {
        $sum = 0;

        foreach (str_split($baseDigits) as $index => $digit) {
            $position = $index + 1;
            $weight = $position % 2 === 0 ? 3 : 1;
            $sum += ((int) $digit) * $weight;
        }

        return (10 - ($sum % 10)) % 10;
    }

    protected function barcodeExists(string $barcode): bool
    {
        return ProductVariant::withTrashed()
            ->where('barcode', $barcode)
            ->exists();
    }
}
