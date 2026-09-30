<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSearchSuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_context_suggests_products_and_brands_with_admin_links(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $brand = Brand::factory()->create(['name' => 'Suggestron']);
        $product = Product::factory()->create([
            'name' => 'Suggestron Laser Printer',
            'brand_id' => $brand->id,
            'is_active' => true,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'reserved' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($director)
            ->getJson(route('catalog.search-suggestions', ['q' => 'Suggestron', 'context' => 'products']))
            ->assertOk();

        $groups = collect($response->json('groups'));

        $productItem = collect($groups->firstWhere('key', 'products')['items'])->first();
        $this->assertSame('Suggestron Laser Printer', $productItem['label']);
        $this->assertStringContainsString('4 in stock', $productItem['meta']);
        $this->assertSame(route('admin.products.show', $product), $productItem['href']);

        $brandItem = collect($groups->firstWhere('key', 'brands')['items'])->first();
        $this->assertSame('Suggestron', $brandItem['label']);
        $this->assertSame(route('admin.products.index', ['brand_id' => $brand->id]), $brandItem['href']);
    }

    public function test_barcodes_context_suggests_variants_with_sku_filter_values(): void
    {
        $rep = User::factory()->create();
        $rep->syncRoles([RoleNames::SALES_REPRESENTATIVE]);

        $product = Product::factory()->create(['name' => 'Suggest Gamepad', 'is_active' => true]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'SUGGEST-PAD-001',
            'quantity' => 7,
            'reserved' => 0,
            'fulfillment_type' => ProductVariant::FULFILLMENT_STOCKED,
        ]);

        $response = $this->actingAs($rep)
            ->getJson(route('catalog.search-suggestions', ['q' => 'SUGGEST-PAD', 'context' => 'barcodes']))
            ->assertOk();

        $item = collect($response->json('groups.0.items'))->first();

        $this->assertSame('SUGGEST-PAD-001', $item['apply_search']);
        $this->assertStringContainsString('SKU SUGGEST-PAD-001', $item['meta']);
        $this->assertStringContainsString('7 available', $item['meta']);
        $this->assertNull($item['href']);
    }

    public function test_multi_word_queries_match_products_with_words_out_of_order(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $brand = Brand::factory()->create(['name' => 'Dell']);
        $product = Product::factory()->create([
            'name' => 'Latitude 5480 Business Laptop',
            'brand_id' => $brand->id,
            'is_active' => true,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'quantity' => 0,
            'reserved' => 0,
            'is_active' => true,
        ]);

        // "Dell" only matches via the brand and "5480" via the name; the old
        // whole-phrase LIKE found nothing. Out of stock must not matter.
        $response = $this->actingAs($director)
            ->getJson(route('catalog.search-suggestions', ['q' => 'Dell 5480', 'context' => 'products']))
            ->assertOk();

        $items = collect($response->json('groups'))->firstWhere('key', 'products')['items'] ?? [];
        $this->assertSame('Latitude 5480 Business Laptop', collect($items)->first()['label']);
    }

    public function test_multi_word_queries_match_barcode_variants_across_name_and_sku(): void
    {
        $rep = User::factory()->create();
        $rep->syncRoles([RoleNames::SALES_REPRESENTATIVE]);

        $product = Product::factory()->create(['name' => 'Latitude 5480 Business Laptop', 'is_active' => true]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'DELL-LAT-5480',
            'quantity' => 3,
            'reserved' => 0,
            'fulfillment_type' => ProductVariant::FULFILLMENT_STOCKED,
        ]);

        $response = $this->actingAs($rep)
            ->getJson(route('catalog.search-suggestions', ['q' => 'dell 5480', 'context' => 'barcodes']))
            ->assertOk();

        $item = collect($response->json('groups.0.items'))->first();
        $this->assertSame('DELL-LAT-5480', $item['apply_search']);
    }

    public function test_short_queries_return_no_groups(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $this->actingAs($director)
            ->getJson(route('catalog.search-suggestions', ['q' => 'a', 'context' => 'products']))
            ->assertOk()
            ->assertExactJson(['groups' => []]);
    }

    public function test_customers_cannot_use_catalog_suggestions(): void
    {
        $customer = User::factory()->create();
        $customer->syncRoles([RoleNames::CUSTOMER]);

        $this->actingAs($customer)
            ->getJson(route('catalog.search-suggestions', ['q' => 'printer', 'context' => 'products']))
            ->assertForbidden();
    }
}
