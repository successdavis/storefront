<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\StorefrontSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontSearchRelevanceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProduct(string $name, Brand $brand, Category $category, string $description = ''): Product
    {
        $product = Product::factory()->create([
            'name' => $name,
            'brand_id' => $brand->id,
            'description' => $description,
            'is_active' => true,
        ]);
        $product->categories()->attach($category->id);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        return $product;
    }

    protected function searchNames(string $query): array
    {
        $result = app(StorefrontSearchService::class)->search(['q' => $query]);

        return collect($result['results']->items())->pluck('name')->all();
    }

    public function test_brand_plus_category_query_ranks_category_products_above_accessories(): void
    {
        $dell = Brand::factory()->create(['name' => 'Dell']);
        $laptops = Category::factory()->create(['name' => 'Laptops', 'slug' => 'laptops']);
        $chargers = Category::factory()->create(['name' => 'Chargers', 'slug' => 'chargers']);

        $this->makeProduct('Dell Latitude 3500', $dell, $laptops);
        $this->makeProduct('Dell Inspiron 15', $dell, $laptops);
        $this->makeProduct('65w Dell Big Mouth Charger', $dell, $chargers, 'Replacement charger for Dell laptops.');

        $names = $this->searchNames('Dell laptops');

        $this->assertContains('Dell Latitude 3500', $names);
        $this->assertContains('Dell Inspiron 15', $names);
        $this->assertSame('65w Dell Big Mouth Charger', end($names), 'Accessory should rank below actual laptops');
        $this->assertNotSame('65w Dell Big Mouth Charger', $names[0]);
    }

    public function test_plural_and_singular_terms_match_each_other(): void
    {
        $hp = Brand::factory()->create(['name' => 'HP']);
        $laptops = Category::factory()->create(['name' => 'Laptop', 'slug' => 'laptop']);

        $this->makeProduct('HP 15 Notebook', $hp, $laptops);

        $this->assertNotEmpty($this->searchNames('laptops'), 'Plural query should match singular category');
    }

    public function test_all_terms_must_match_when_results_exist(): void
    {
        $dell = Brand::factory()->create(['name' => 'Dell']);
        $apple = Brand::factory()->create(['name' => 'Apple']);
        $laptops = Category::factory()->create(['name' => 'Laptops', 'slug' => 'laptops']);

        $this->makeProduct('Dell Latitude 3500', $dell, $laptops);
        $this->makeProduct('Apple MacBook Air', $apple, $laptops);

        $names = $this->searchNames('Dell laptops');

        $this->assertContains('Dell Latitude 3500', $names);
        $this->assertNotContains('Apple MacBook Air', $names, 'Products missing a term should be excluded when full matches exist');
    }

    public function test_falls_back_to_partial_matches_when_no_product_matches_every_term(): void
    {
        $dell = Brand::factory()->create(['name' => 'Dell']);
        $laptops = Category::factory()->create(['name' => 'Laptops', 'slug' => 'laptops']);

        $this->makeProduct('Dell Latitude 3500', $dell, $laptops);

        $names = $this->searchNames('Dell quantumfoo');

        $this->assertContains('Dell Latitude 3500', $names, 'Unmatchable extra term should relax instead of returning nothing');
    }

    public function test_exact_name_match_ranks_first(): void
    {
        $dell = Brand::factory()->create(['name' => 'Dell']);
        $chargers = Category::factory()->create(['name' => 'Chargers', 'slug' => 'chargers']);
        $laptops = Category::factory()->create(['name' => 'Laptops', 'slug' => 'laptops']);

        $this->makeProduct('Dell Latitude 3500', $dell, $laptops, 'Latitude with charger included');
        $this->makeProduct('Dell Charger', $dell, $chargers);

        $names = $this->searchNames('Dell charger');

        $this->assertSame('Dell Charger', $names[0]);
    }
}
