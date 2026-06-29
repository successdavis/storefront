<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_exposes_canonical_metadata_and_product_schema(): void
    {
        $category = Category::factory()->create([
            'name' => 'Laptops',
            'slug' => 'laptops',
        ]);
        $product = Product::factory()->create([
            'name' => 'Business Laptop Pro',
            'slug' => 'business-laptop-pro',
            'meta_title' => 'Business Laptop Pro for Work',
            'meta_description' => 'Shop the Business Laptop Pro with warranty and fast delivery.',
            'is_active' => true,
        ]);
        $product->categories()->attach($category->id);
        ProductVariant::factory()->for($product)->create([
            'sku' => 'BLP-001',
            'quantity' => 5,
            'reserved' => 0,
            'regular_price' => 450000,
            'is_active' => true,
        ]);

        $response = $this->get(route('store.product', $product->slug));

        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $productSchema = collect($props['structuredData'])->firstWhere('@type', 'Product');
        $breadcrumbSchema = collect($props['structuredData'])->firstWhere('@type', 'BreadcrumbList');

        $this->assertSame('Business Laptop Pro for Work', $props['seo']['title']);
        $this->assertSame(route('store.product', $product->slug), $props['seo']['canonical']);
        $this->assertSame('product', $props['seo']['type']);
        $this->assertSame('Product', $productSchema['@type']);
        $this->assertSame('Business Laptop Pro', $productSchema['name']);
        $this->assertSame('BLP-001', $productSchema['sku']);
        $this->assertSame('BreadcrumbList', $breadcrumbSchema['@type']);
    }

    public function test_category_slug_route_indexes_and_legacy_id_route_redirects(): void
    {
        $category = Category::factory()->create([
            'name' => 'Phones',
            'slug' => 'phones',
            'meta_title' => 'Phones and Mobile Devices',
            'meta_description' => 'Shop phones and mobile devices.',
        ]);
        $product = Product::factory()->create(['is_active' => true]);
        $product->categories()->attach($category->id);
        ProductVariant::factory()->for($product)->create([
            'quantity' => 2,
            'regular_price' => 150000,
            'is_active' => true,
        ]);

        $this->get(route('store.category', ['category' => $category->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Storefront/Collection')
                ->where('seo.title', 'Phones and Mobile Devices')
                ->where('seo.canonical', route('store.category', ['category' => $category->slug]))
            );

        $this->get(route('store.category.legacy', $category))
            ->assertRedirect(route('store.category', ['category' => $category->slug]))
            ->assertStatus(301);
    }

    public function test_search_and_cart_are_noindex_follow(): void
    {
        Product::factory()
            ->has(ProductVariant::factory()->state([
                'quantity' => 3,
                'regular_price' => 120000,
                'is_active' => true,
            ]), 'variants')
            ->create([
                'name' => 'Searchable Laptop',
                'slug' => 'searchable-laptop',
                'is_active' => true,
            ]);

        $searchProps = $this->get(route('store.search', ['q' => 'laptop']))
            ->assertOk()
            ->viewData('page')['props'];

        $cartProps = $this->get(route('store.cart'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('noindex,follow', $searchProps['seo']['robots']);
        $this->assertSame('noindex,follow', $cartProps['seo']['robots']);
    }

    public function test_sitemap_lists_public_active_catalog_urls_only(): void
    {
        $category = Category::factory()->create([
            'name' => 'Accessories',
            'slug' => 'accessories',
        ]);
        $activeProduct = Product::factory()->create([
            'slug' => 'active-product',
            'is_active' => true,
        ]);
        $activeProduct->categories()->attach($category->id);
        ProductVariant::factory()->for($activeProduct)->create([
            'quantity' => 4,
            'regular_price' => 50000,
            'is_active' => true,
        ]);

        $inactiveProduct = Product::factory()->create([
            'slug' => 'inactive-product',
            'is_active' => false,
        ]);
        ProductVariant::factory()->for($inactiveProduct)->create([
            'quantity' => 4,
            'regular_price' => 50000,
            'is_active' => true,
        ]);

        $response = $this->get(route('seo.sitemap'));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('store.home'), false)
            ->assertSee(route('store.category', ['category' => $category->slug]), false)
            ->assertSee(route('store.product', $activeProduct->slug), false)
            ->assertDontSee(route('store.product', $inactiveProduct->slug), false)
            ->assertDontSee(route('store.search'), false)
            ->assertDontSee(route('store.cart'), false);
    }

    public function test_robots_points_to_sitemap_and_blocks_low_value_sections(): void
    {
        $this->get(route('seo.robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin/', false)
            ->assertSee('Disallow: /store/search', false)
            ->assertSee('Sitemap: ' . route('seo.sitemap'), false);
    }
}
