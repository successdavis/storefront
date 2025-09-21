<?php

namespace App\Repositories;

use App\Models\Product;

class ProductRepository
{
    public function allWithRelations()
    {
        return Product::with(['category', 'brand', 'variants'])->get();
    }

    public function findWithRelations(int $id)
    {
        return Product::with(['category', 'brand', 'variants.variantValues'])->findOrFail($id);
    }

    public function create(array $data)
    {
        return Product::create($data);
    }

    public function update(int $id, array $data)
    {
        $product = Product::findOrFail($id);
        $product->update($data);
        return $product;
    }

    public function delete(int $id)
    {
        return Product::destroy($id);
    }
}
