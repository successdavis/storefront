<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Support\Str;

final class SkuGenerator
{
    public function normalize(?string $raw): string
    {
        $s = strtoupper(trim((string) $raw));
        $s = preg_replace('/\s+/', '-', $s);      // collapse spaces to dashes
        $s = preg_replace('/[^A-Z0-9\-]/', '', $s); // keep A–Z, 0–9, dash
        return trim($s, '-');
    }

    public function exists(?int $storeId, string $sku, ?int $ignoreId = null): bool
    {
        $q = ProductVariant::query()->where('sku', $sku);
        if ($storeId) $q->where('store_id', $storeId);
        if ($ignoreId) $q->where('id', '!=', $ignoreId);
        return $q->exists();
    }

    /**
     * Build a human SKU stem from product context and variant attributes.
     * Pass any short hints you like. Keep it readable and under 40–50 chars.
     */
    public function makeStem(string $brandCode, string $productName, array $attrLabels = []): string
    {
        $brand = $this->normalize(Str::limit($brandCode, 10, ''));
        $name  = $this->normalize(Str::limit($productName, 20, ''));
        $attr  = $this->normalize(collect($attrLabels)->filter()->join('-'));
        $stem  = $attr ? "$brand-$name-$attr" : "$brand-$name";
        return Str::of($stem)->substr(0, 48)->toString();
    }

    /**
     * Return a unique SKU, adding -001, -002, ... as needed.
     */
    public function uniqueFromStem(?int $storeId, string $stem): string
    {
        $stem = $this->normalize($stem);
        $n = 1;
        do {
            $sku = sprintf('%s-%03d', $stem, $n);
            $n++;
        } while ($this->exists($storeId, $sku));
        return $sku;
    }

    /**
     * If user typed a SKU, normalize and either accept it or propose the next free one.
     */
    public function acceptOrSuggest(?int $storeId, string $userSku, ?int $ignoreId = null): array
    {
        $norm = $this->normalize($userSku);
        if (!$this->exists($storeId, $norm, $ignoreId)) {
            return ['accepted' => true, 'sku' => $norm];
        }
        // Suggest the next free numeric suffix
        $base = preg_replace('/-\d{3}$/', '', $norm);
        return ['accepted' => false, 'sku' => $this->uniqueFromStem($storeId, $base)];
    }
}
