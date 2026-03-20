<?php

namespace App\Domain\Inventory\Support;

use App\Models\ProductVariant;

class VariantNameFormatter
{
    public function format(ProductVariant $variant): string
    {
        $variant->loadMissing([
            'product:id,name',
            'values:id,variant_type_id,value',
            'values.type:id,name',
        ]);

        $productName = $variant->product?->name ?? 'Unknown Product';

        $attributes = $variant->values
            ->pluck('value')
            ->filter(fn ($value) => filled($value))
            ->implode(', ');

        if ($attributes === '') {
            return $productName;
        }

        return "{$productName} - {$attributes}";
    }
}
