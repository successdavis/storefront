<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminProductFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected User $director;

    protected function setUp(): void
    {
        parent::setUp();

        $this->director = User::factory()->create();
        $this->director->syncRoles([RoleNames::DIRECTOR]);
    }

    protected function makeProduct(array $productAttributes = [], array $variantAttributes = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'is_active' => true,
            'featured' => false,
        ], $productAttributes));

        ProductVariant::factory()->create(array_merge([
            'product_id' => $product->id,
            'is_active' => true,
            'quantity' => 5,
        ], $variantAttributes));

        return $product;
    }

    protected function resultNames(array $query): array
    {
        $response = $this->actingAs($this->director)
            ->get(route('admin.products.index', $query))
            ->assertOk();

        $names = [];
        $response->assertInertia(function (Assert $page) use (&$names) {
            $names = collect($page->toArray()['props']['products']['data'])->pluck('name')->all();

            return $page->component('Admin/Products/Index');
        });

        return $names;
    }

    public function test_featured_filter(): void
    {
        $this->makeProduct(['name' => 'Featured Product', 'featured' => true]);
        $this->makeProduct(['name' => 'Plain Product']);

        $this->assertSame(['Featured Product'], $this->resultNames(['featured' => '1']));
        $this->assertSame(['Plain Product'], $this->resultNames(['featured' => '0']));
    }

    public function test_status_filter(): void
    {
        $this->makeProduct(['name' => 'Live Product']);
        $this->makeProduct(['name' => 'Draft Product', 'is_active' => false]);

        $this->assertSame(['Live Product'], $this->resultNames(['status' => 'published']));
        $this->assertSame(['Draft Product'], $this->resultNames(['status' => 'draft']));
    }

    public function test_stock_filter(): void
    {
        $this->makeProduct(['name' => 'Stocked Product'], ['quantity' => 3]);
        $this->makeProduct(['name' => 'Empty Product'], ['quantity' => 0]);

        $this->assertSame(['Stocked Product'], $this->resultNames(['stock' => 'in']));
        $this->assertSame(['Empty Product'], $this->resultNames(['stock' => 'out']));
    }

    public function test_brand_and_category_filters(): void
    {
        $dell = Brand::factory()->create(['name' => 'Dell']);
        $hp = Brand::factory()->create(['name' => 'HP']);
        $laptops = Category::factory()->create(['name' => 'Laptops']);

        $dellProduct = $this->makeProduct(['name' => 'Dell Product', 'brand_id' => $dell->id]);
        $dellProduct->categories()->attach($laptops->id);
        $this->makeProduct(['name' => 'HP Product', 'brand_id' => $hp->id]);

        $this->assertSame(['Dell Product'], $this->resultNames(['brand_id' => (string) $dell->id]));
        $this->assertSame(['Dell Product'], $this->resultNames(['category_id' => (string) $laptops->id]));
    }

    public function test_fulfillment_filter(): void
    {
        $this->makeProduct(['name' => 'Dropship Product'], ['fulfillment_type' => ProductVariant::FULFILLMENT_DROPSHIPPING]);
        $this->makeProduct(['name' => 'Stocked Only Product'], ['fulfillment_type' => ProductVariant::FULFILLMENT_STOCKED]);

        $this->assertSame(['Dropship Product'], $this->resultNames(['fulfillment' => 'dropshipping']));
        $this->assertSame(['Stocked Only Product'], $this->resultNames(['fulfillment' => 'stocked']));
    }

    public function test_on_sale_filter_uses_discount_rules(): void
    {
        $saleProduct = $this->makeProduct(['name' => 'Discounted Product'], ['regular_price' => 100]);
        $this->makeProduct(['name' => 'Full Price Product'], ['regular_price' => 100]);

        $discount = Discount::query()->create([
            'name' => 'Product 10 Off',
            'description' => 'Automatic product discount',
            'code' => null,
            'type' => Discount::TYPE_PERCENTAGE,
            'value' => 10,
            'application_method' => Discount::APPLICATION_LINE_ITEM,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'customer_scope' => Discount::CUSTOMER_SCOPE_ALL,
            'priority' => 0,
            'is_active' => true,
        ]);
        $discount->products()->attach($saleProduct->id);

        $this->assertSame(['Discounted Product'], $this->resultNames(['on_sale' => '1']));
        $this->assertSame(['Full Price Product'], $this->resultNames(['on_sale' => '0']));
    }

    public function test_filters_combine_with_search(): void
    {
        $this->makeProduct(['name' => 'Alpha Widget', 'featured' => true]);
        $this->makeProduct(['name' => 'Alpha Gadget']);
        $this->makeProduct(['name' => 'Beta Widget', 'featured' => true]);

        $this->assertSame(['Alpha Widget'], $this->resultNames(['search' => 'Alpha', 'featured' => '1']));
    }

    public function test_invalid_filter_values_are_ignored(): void
    {
        $this->makeProduct(['name' => 'Any Product']);

        $this->assertSame(['Any Product'], $this->resultNames(['status' => 'bogus', 'stock' => 'nope', 'featured' => 'x']));
    }
}
