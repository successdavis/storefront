<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;

class ProductVariantService
{
    public function getByProduct(Product $product)
    {
        return $product->variants()->with('variantValues')->get();
    }

    public function create(Product $product, array $data)
    {
        $variant = $product->variants()->create([
            'sku' => $data['sku'],
            'price' => $data['price'],
            'quantity' => $data['quantity'],
            'barcode' => $data['barcode'],
        ]);

        if (isset($data['variant_values'])) {
            $variant->variantValues()->sync($data['variant_values']);
        }

        return $variant->load('variantValues');
    }

    public function update(ProductVariant $variant, array $data)
    {
        $variant->update($data);

        if (isset($data['variant_values'])) {
            $variant->variantValues()->sync($data['variant_values']);
        }

        return $variant->load('variantValues');
    }

    public function delete(ProductVariant $variant)
    {
        $variant->delete();
    }
}
