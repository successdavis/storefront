<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategorySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_update_syncs_categories(): void
    {
        $oldCategory = Category::factory()->create();
        $newCategory = Category::factory()->create();
        $product = Product::factory()->create(['name' => 'Original Product']);
        $product->categories()->sync([$oldCategory->id]);

        app(ProductService::class)->update($product, [
            'name' => 'Updated Product',
            'category_ids' => [$newCategory->id],
        ]);

        $this->assertSame(
            [$newCategory->id],
            $product->fresh()->categories()->pluck('categories.id')->all()
        );
    }
}
