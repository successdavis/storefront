<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\SlugService;
use Illuminate\Support\Facades\DB;

class ProductObserver
{
    public function creating($product)
    {
        if (empty($product->slug)) {
            $product->slug = app(SlugService::class)->makeUnique($product->name, 'products');
        }
    }

    public function updating($product)
    {
        if (!$product->isDirty('slug') && !$product->isDirty('name')) return;

        // If products are immutable, abort edits to slug
        // throw ValidationException::withMessages(['slug' => 'Slug cannot be changed']);

        // If you do allow changes:
        DB::transaction(function () use ($product) {
            $old = $product->getOriginal('slug');
            if ($product->isDirty('name') && empty($product->slug)) {
                $product->slug = app(SlugService::class)->makeUnique($product->name, 'products');
            }
            if ($old && $old !== $product->slug) {
                $product->slugHistories()->create(['slug' => $old]);
            }
        });
    }
}
