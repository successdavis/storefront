<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantType;
use App\Models\VariantValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_page_derives_dynamic_filters_from_the_matching_catalog_context(): void
    {
        $computers = Category::factory()->create(['name' => 'Computers', 'slug' => 'computers']);
        $apparel = Category::factory()->create(['name' => 'Apparel', 'slug' => 'apparel']);

        $dell = Brand::factory()->create(['name' => 'Dell', 'slug' => 'dell']);
        $nike = Brand::factory()->create(['name' => 'Nike', 'slug' => 'nike']);

        $ram = VariantType::factory()->create(['name' => 'RAM', 'slug' => 'ram']);
        $storage = VariantType::factory()->create(['name' => 'Storage', 'slug' => 'storage']);
        $size = VariantType::factory()->create(['name' => 'Size', 'slug' => 'size']);
        $color = VariantType::factory()->create(['name' => 'Color', 'slug' => 'color']);

        $this->createCatalogProduct('Business Laptop', $computers, $dell, [
            $ram->id => '8GB',
            $storage->id => '512GB SSD',
        ]);

        $this->createCatalogProduct('Gaming Laptop', $computers, $dell, [
            $ram->id => '16GB',
            $storage->id => '1TB SSD',
        ]);

        $this->createCatalogProduct('Running Shirt', $apparel, $nike, [
            $size->id => 'L',
            $color->id => 'Blue',
        ]);

        $response = $this->get(route('store.search', ['q' => 'laptop']));
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $labels = collect($props['filterGroups'])->pluck('label');

        $this->assertTrue($labels->contains('RAM'));
        $this->assertTrue($labels->contains('Storage'));
        $this->assertTrue($labels->contains('Brand'));
        $this->assertTrue($labels->contains('Category'));
        $this->assertFalse($labels->contains('Size'));
    }

    public function test_search_filters_by_dynamic_attribute_and_keeps_discount_aware_card_prices(): void
    {
        $category = Category::factory()->create(['name' => 'Computers', 'slug' => 'computers']);
        $brand = Brand::factory()->create(['name' => 'Lenovo', 'slug' => 'lenovo']);
        $ram = VariantType::factory()->create(['name' => 'RAM', 'slug' => 'ram']);

        $budgetLaptop = $this->createCatalogProduct('Budget Laptop', $category, $brand, [
            $ram->id => '8GB',
        ], [
            'regular_price' => 100000,
            'sale_starts_at' => null,
            'sale_ends_at' => null,
        ]);

        $proLaptop = $this->createCatalogProduct('Pro Laptop', $category, $brand, [
            $ram->id => '16GB',
        ], [
            'regular_price' => 120000,
            'sale_starts_at' => null,
            'sale_ends_at' => null,
        ]);

        $discount = Discount::query()->create([
            'name' => 'Laptop Markdown',
            'description' => 'Automatic search pricing rule',
            'code' => null,
            'type' => Discount::TYPE_PERCENTAGE,
            'value' => 10,
            'application_method' => Discount::APPLICATION_LINE_ITEM,
            'min_order_amount' => null,
            'usage_limit' => null,
            'usage_limit_per_user' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'customer_scope' => Discount::CUSTOMER_SCOPE_ALL,
            'priority' => 0,
            'is_active' => true,
        ]);
        $discount->products()->attach($proLaptop->id);

        $response = $this->get(route('store.search', [
            'q' => 'laptop',
            'ram' => '16GB',
        ]));

        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $results = collect($props['results']['data']);

        $this->assertCount(1, $results);
        $this->assertSame('Pro Laptop', $results->first()['name']);
        $this->assertSame(108000.0, (float) $results->first()['price']['current']);
        $this->assertTrue((bool) $results->first()['price']['has_discount']);
        $this->assertTrue(collect($props['activeFilters'])->pluck('label')->contains('RAM: 16GB'));
    }

    public function test_search_suggestions_return_grouped_query_product_category_and_brand_matches(): void
    {
        $category = Category::factory()->create(['name' => 'Laptop Accessories', 'slug' => 'laptop-accessories']);
        $brand = Brand::factory()->create(['name' => 'LaptopPro', 'slug' => 'laptoppro']);
        $storage = VariantType::factory()->create(['name' => 'Storage', 'slug' => 'storage']);

        $this->createCatalogProduct('LaptopPro Laptop Sleeve', $category, $brand, [
            $storage->id => '256GB SSD',
        ]);

        $response = $this->getJson(route('store.search.suggestions', ['q' => 'laptop']));

        $response->assertOk();

        $groups = collect($response->json('groups'));
        $keys = $groups->pluck('key');

        $this->assertTrue($keys->contains('queries'));
        $this->assertTrue($keys->contains('products'));
        $this->assertTrue($keys->contains('categories'));
        $this->assertTrue($keys->contains('brands'));
    }

    protected function createCatalogProduct(
        string $name,
        Category $category,
        Brand $brand,
        array $valuesByTypeId,
        array $variantOverrides = []
    ): Product {
        $product = Product::factory()->create([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'brand_id' => $brand->id,
        ]);

        $product->categories()->attach($category->id);

        $variant = ProductVariant::factory()->create(array_merge([
            'product_id' => $product->id,
            'quantity' => 6,
            'reserved' => 0,
            'regular_price' => 150000,
            'sale_starts_at' => null,
            'sale_ends_at' => null,
            'is_active' => true,
        ], $variantOverrides));

        $valueIds = collect($valuesByTypeId)
            ->map(function (string $value, int $typeId) {
                return VariantValue::factory()->create([
                    'variant_type_id' => $typeId,
                    'value' => $value,
                ])->id;
            })
            ->values()
            ->all();

        $variant->values()->sync($valueIds);

        return $product->fresh(['brand', 'categories', 'variants.values.type']);
    }
}
