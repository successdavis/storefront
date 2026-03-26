<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantType;
use App\Models\VariantValue;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCategoryPriceListReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_preview_category_price_list_with_variant_rows_using_live_stock_and_price_logic(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $selectedCategory = Category::factory()->create(['name' => 'Laptops']);
        $otherCategory = Category::factory()->create(['name' => 'Phones']);
        $brand = Brand::factory()->create();

        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Hp ProBook 650 G2',
            'is_active' => true,
        ]);
        $product->categories()->attach($selectedCategory->id);

        $otherProduct = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Samsung A55',
            'is_active' => true,
        ]);
        $otherProduct->categories()->attach($otherCategory->id);

        $firstVariant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'HP-650-BLK-8GB',
            'quantity' => 10,
            'reserved' => 3,
            'regular_price' => 100000,
            'sale_price' => null,
        ]);
        $secondVariant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'HP-650-SLV-16GB',
            'quantity' => 5,
            'reserved' => 0,
            'regular_price' => 120000,
            'sale_price' => null,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $otherProduct->id,
            'sku' => 'SAM-A55-128',
            'quantity' => 40,
            'reserved' => 2,
            'regular_price' => 200000,
        ]);

        $this->attachVariantValues($firstVariant, [
            ['Color', 'Black & Ash'],
            ['Memory', '8gb'],
        ]);
        $this->attachVariantValues($secondVariant, [
            ['Color', 'Silver'],
            ['Memory', '16gb'],
        ]);

        $discount = Discount::query()->create([
            'name' => 'Laptop Markdown',
            'type' => Discount::TYPE_PERCENTAGE,
            'application_method' => Discount::APPLICATION_LINE_ITEM,
            'value' => 20,
            'customer_scope' => Discount::CUSTOMER_SCOPE_ALL,
            'priority' => 50,
            'is_active' => true,
        ]);
        $discount->categories()->attach($selectedCategory->id);

        $this->actingAs($director)
            ->get(route('admin.reports.category-price-list.index', [
                'category_id' => $selectedCategory->id,
                'sort' => 'alphabetical',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/CategoryPriceList')
                ->where('report.summary.selected_category.name', 'Laptops')
                ->where('report.summary.total_rows', 2)
                ->where('report.preview.data.0.product_name', 'Hp ProBook 650 G2')
                ->where('report.preview.data.0.variant_name', 'Color: Black & Ash / Memory: 8gb')
                ->where('report.preview.data.0.quantity_available', 7)
                ->where('report.preview.data.0.sales_price', 80000)
                ->where('report.preview.data.1.variant_name', 'Color: Silver / Memory: 16gb')
            );
    }

    public function test_export_returns_pdf_response_and_can_filter_to_in_stock_rows_only(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $category = Category::factory()->create(['name' => 'Accessories']);
        $brand = Brand::factory()->create();

        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Dell Dock',
            'is_active' => true,
        ]);
        $product->categories()->attach($category->id);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'DOCK-IN',
            'quantity' => 9,
            'reserved' => 1,
            'regular_price' => 45000,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'DOCK-OUT',
            'quantity' => 2,
            'reserved' => 2,
            'regular_price' => 47000,
        ]);

        $response = $this->actingAs($director)
            ->get(route('admin.reports.category-price-list.export', [
                'category_id' => $category->id,
                'in_stock_only' => 1,
            ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('price-list-accessories-', (string) $response->headers->get('content-disposition'));
    }

    public function test_customers_cannot_access_category_price_list_report_routes(): void
    {
        $customer = User::factory()->create();
        $customer->syncRoles([RoleNames::CUSTOMER]);

        $this->actingAs($customer)
            ->get(route('admin.reports.category-price-list.index'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('admin.reports.category-price-list.export', ['category_id' => 1]))
            ->assertForbidden();
    }

    protected function attachVariantValues(ProductVariant $variant, array $pairs): void
    {
        $valueIds = collect($pairs)->map(function (array $pair) {
            $type = VariantType::factory()->create(['name' => $pair[0]]);

            return VariantValue::factory()->create([
                'variant_type_id' => $type->id,
                'value' => $pair[1],
            ])->id;
        });

        $variant->values()->attach($valueIds);
    }
}
