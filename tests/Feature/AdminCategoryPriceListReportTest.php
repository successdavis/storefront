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
use App\Services\Reports\CategoryPriceListReportService;
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
                'sort' => 'default',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/CategoryPriceList')
                ->where('report.summary.selected_category.name', 'Laptops')
                ->where('report.summary.total_rows', 2)
                ->where('report.summary.sort', 'default')
                ->where('report.preview.data.0.product_name', 'Hp ProBook 650 G2')
                ->where('report.preview.data.0.variant_name', 'Color: Black & Ash / Memory: 8gb')
                ->where('report.preview.data.0.quantity_available', 7)
                ->where('report.preview.data.0.original_price', 100000)
                ->where('report.preview.data.0.final_price', 80000)
                ->where('report.preview.data.0.sales_price', 80000)
                ->where('report.preview.data.0.has_active_discount', true)
                ->where('report.preview.data.0.discount_label', 'Laptop Markdown')
                ->where('report.preview.data.0.discount_display_label', '-20%')
                ->where('report.preview.data.1.variant_name', 'Color: Silver / Memory: 16gb')
            );
    }

    public function test_price_sorting_works_both_directions_and_export_uses_identical_ordering(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $category = Category::factory()->create(['name' => 'Monitors']);
        $brand = Brand::factory()->create();

        $cheapProduct = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Budget Monitor',
            'is_active' => true,
        ]);
        $midProduct = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Office Monitor',
            'is_active' => true,
        ]);
        $highProduct = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Premium Monitor',
            'is_active' => true,
        ]);

        $cheapProduct->categories()->attach($category->id);
        $midProduct->categories()->attach($category->id);
        $highProduct->categories()->attach($category->id);

        $cheapVariant = ProductVariant::factory()->create([
            'product_id' => $cheapProduct->id,
            'sku' => 'MON-CHEAP',
            'quantity' => 9,
            'reserved' => 1,
            'regular_price' => 90000,
        ]);
        $midVariant = ProductVariant::factory()->create([
            'product_id' => $midProduct->id,
            'sku' => 'MON-MID',
            'quantity' => 4,
            'reserved' => 0,
            'regular_price' => 200000,
        ]);
        $highVariant = ProductVariant::factory()->create([
            'product_id' => $highProduct->id,
            'sku' => 'MON-HIGH',
            'quantity' => 3,
            'reserved' => 0,
            'regular_price' => 150000,
        ]);

        $midDiscount = Discount::query()->create([
            'name' => 'Office Monitor Promo',
            'type' => Discount::TYPE_FIXED_AMOUNT,
            'application_method' => Discount::APPLICATION_LINE_ITEM,
            'value' => 120000,
            'customer_scope' => Discount::CUSTOMER_SCOPE_ALL,
            'priority' => 50,
            'is_active' => true,
        ]);
        $midDiscount->products()->attach($midProduct->id);

        $lowToHighResponse = $this->actingAs($director)
            ->get(route('admin.reports.category-price-list.index', [
                'category_id' => $category->id,
                'sort' => 'price_asc',
            ]));

        $lowToHighResponse
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.summary.sort', 'price_asc')
                ->where('report.preview.data.0.variant_id', $midVariant->id)
                ->where('report.preview.data.0.final_price', 80000)
                ->where('report.preview.data.1.variant_id', $cheapVariant->id)
                ->where('report.preview.data.2.variant_id', $highVariant->id)
            );

        $highToLowResponse = $this->actingAs($director)
            ->get(route('admin.reports.category-price-list.index', [
                'category_id' => $category->id,
                'sort' => 'price_desc',
            ]));

        $highToLowResponse
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.summary.sort', 'price_desc')
                ->where('report.preview.data.0.variant_id', $highVariant->id)
                ->where('report.preview.data.1.variant_id', $cheapVariant->id)
                ->where('report.preview.data.2.variant_id', $midVariant->id)
            );

        $exportPayload = app(CategoryPriceListReportService::class)->exportPayload([
            'category_id' => $category->id,
            'sort' => 'price_desc',
        ], $director);

        $this->assertSame(
            [$highVariant->id, $cheapVariant->id, $midVariant->id],
            collect($exportPayload['rows'])->pluck('variant_id')->all(),
        );
    }

    public function test_report_rows_expose_discount_price_fields_for_fixed_and_non_discounted_variants(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $category = Category::factory()->create(['name' => 'Printers']);
        $brand = Brand::factory()->create();

        $discountedProduct = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'LaserJet Pro',
            'is_active' => true,
        ]);
        $regularProduct = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'InkTank Basic',
            'is_active' => true,
        ]);

        $discountedProduct->categories()->attach($category->id);
        $regularProduct->categories()->attach($category->id);

        $discountedVariant = ProductVariant::factory()->create([
            'product_id' => $discountedProduct->id,
            'sku' => 'LASER-PRO',
            'quantity' => 6,
            'reserved' => 1,
            'regular_price' => 75000,
        ]);
        $regularVariant = ProductVariant::factory()->create([
            'product_id' => $regularProduct->id,
            'sku' => 'INK-BASIC',
            'quantity' => 8,
            'reserved' => 0,
            'regular_price' => 68000,
        ]);

        $fixedDiscount = Discount::query()->create([
            'name' => 'Printer Cash Discount',
            'type' => Discount::TYPE_FIXED_AMOUNT,
            'application_method' => Discount::APPLICATION_LINE_ITEM,
            'value' => 5000,
            'customer_scope' => Discount::CUSTOMER_SCOPE_ALL,
            'priority' => 50,
            'is_active' => true,
        ]);
        $fixedDiscount->products()->attach($discountedProduct->id);

        $previewResponse = $this->actingAs($director)
            ->get(route('admin.reports.category-price-list.index', [
                'category_id' => $category->id,
                'sort' => 'default',
            ]));

        $previewResponse
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.preview.data.0.variant_id', $regularVariant->id)
                ->where('report.preview.data.0.original_price', 68000)
                ->where('report.preview.data.0.final_price', 68000)
                ->where('report.preview.data.0.has_active_discount', false)
                ->where('report.preview.data.0.discount_display_label', null)
                ->where('report.preview.data.1.variant_id', $discountedVariant->id)
                ->where('report.preview.data.1.original_price', 75000)
                ->where('report.preview.data.1.final_price', 70000)
                ->where('report.preview.data.1.has_active_discount', true)
                ->where('report.preview.data.1.discount_label', 'Printer Cash Discount')
                ->where('report.preview.data.1.discount_display_label', '-₦5,000')
            );

        $previewRows = app(CategoryPriceListReportService::class)
            ->preview(['category_id' => $category->id, 'sort' => 'default'])
            ?->getCollection()
            ->map(fn (array $row) => [
                'variant_id' => $row['variant_id'],
                'original_price' => $row['original_price'],
                'final_price' => $row['final_price'],
                'has_active_discount' => $row['has_active_discount'],
                'discount_display_label' => $row['discount_display_label'],
            ])
            ->values()
            ->all();

        $exportRows = collect(app(CategoryPriceListReportService::class)->exportPayload([
            'category_id' => $category->id,
            'sort' => 'default',
        ], $director)['rows'])
            ->map(fn (array $row) => [
                'variant_id' => $row['variant_id'],
                'original_price' => $row['original_price'],
                'final_price' => $row['final_price'],
                'has_active_discount' => $row['has_active_discount'],
                'discount_display_label' => $row['discount_display_label'],
            ])
            ->values()
            ->all();

        $this->assertSame($previewRows, $exportRows);
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

    public function test_invalid_sort_input_is_rejected_safely(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $this->from(route('admin.reports.category-price-list.index'))
            ->actingAs($director)
            ->get(route('admin.reports.category-price-list.index', ['sort' => 'totally_invalid']))
            ->assertRedirect(route('admin.reports.category-price-list.index'))
            ->assertSessionHasErrors(['sort']);
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
