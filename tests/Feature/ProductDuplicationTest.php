<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDuplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_creates_inventory_safe_variant_copies(): void
    {
        $brand = Brand::factory()->create();
        $categories = Category::factory()->count(2)->create();

        $product = Product::factory()
            ->withBrand()
            ->create([
                'brand_id' => $brand->id,
                'name' => 'Laptop Charger',
                'is_active' => true,
                'featured' => true,
            ]);

        $product->categories()->sync($categories->pluck('id')->all());

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'LAP-CHARGER-01',
            'barcode' => '1234567890123',
            'quantity' => 9,
            'regular_price' => 25000,
            'last_purchase_price' => 14000,
            'is_active' => true,
        ]);

        $variant->update([
            'reserved' => 2,
            'average_cost' => 14000,
            'total_cost_on_hand' => 126000,
        ]);

        $copy = app(ProductService::class)->duplicate($product);

        $this->assertNotSame($product->id, $copy->id);
        $this->assertFalse((bool) $copy->is_active);
        $this->assertFalse((bool) $copy->featured);
        $this->assertNotSame($product->slug, $copy->slug);
        $originalCategoryIds = $product->categories()->pluck('categories.id')->sort()->values()->all();
        $copiedCategoryIds = $copy->categories()->pluck('categories.id')->sort()->values()->all();

        $this->assertSame($originalCategoryIds, $copiedCategoryIds);

        $copiedVariant = $copy->variants()->first();

        $this->assertNotNull($copiedVariant);
        $this->assertNotSame($variant->id, $copiedVariant->id);
        $this->assertNotSame($variant->sku, $copiedVariant->sku);
        $this->assertNotEmpty($copiedVariant->barcode);
        $this->assertNotSame($variant->barcode, $copiedVariant->barcode);
        $this->assertSame(0, (int) $copiedVariant->quantity);
        $this->assertSame(0, (int) $copiedVariant->reserved);
        $this->assertSame(0.0, (float) $copiedVariant->average_cost);
        $this->assertSame(0.0, (float) $copiedVariant->total_cost_on_hand);
        $this->assertSame((float) $variant->regular_price, (float) $copiedVariant->regular_price);
        $this->assertSame((float) $variant->last_purchase_price, (float) $copiedVariant->last_purchase_price);
    }
}
