<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\VariantType;
use App\Models\VariantValue;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryAuditAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_representative_can_access_and_submit_stock_audits_from_sales_workspace(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([RoleNames::SALES_REPRESENTATIVE]);

        $variant = $this->createVariantWithOptions();

        $this->actingAs($user)
            ->get(route('sales.inventory.stock-audit.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InventoryStockAudit')
                ->where('routes.index', route('sales.inventory.stock-audit.index'))
                ->where('routes.mobile', route('sales.inventory.stock-audit.mobile'))
            );

        $this->actingAs($user)
            ->get(route('sales.inventory.stock-audit.mobile'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InventoryStockAuditMobile')
                ->where('routes.store', route('sales.inventory.stock-audit.store'))
            );

        $this->actingAs($user)
            ->post(route('sales.inventory.stock-audit.store'), [
                'counts' => [
                    [
                        'variant_id' => $variant->id,
                        'physical_quantity' => 11,
                    ],
                ],
                'source' => 'manual',
            ])
            ->assertRedirect(route('sales.inventory.stock-audit.index', ['session_id' => 1]));

        $this->assertDatabaseHas('stock_audit_sessions', [
            'id' => 1,
            'started_by' => $user->id,
            'status' => 'submitted',
        ]);
    }

    public function test_stock_adjustment_pages_expose_readable_variant_labels_alongside_sku(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $variant = $this->createVariantWithOptions();

        $adjustment = StockAdjustment::query()->create([
            'variant_id' => $variant->id,
            'previous_quantity' => 10,
            'adjusted_quantity' => -2,
            'reason' => 'count_discrepancy',
            'employee_id' => $director->id,
            'adjusted_at' => now(),
            'status' => StockAdjustment::STATUS_PENDING,
        ]);

        $expectedLabel = 'Hp ProBook 650 G2 - Black & Ash, 8gb, Intel Core i5, 15 Inch, Windows 11, Light';

        $this->actingAs($director)
            ->get(route('admin.stock-adjustments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StockAdjustments/Index')
                ->where('adjustments.data.0.variant_label', $expectedLabel)
                ->where('adjustments.data.0.variant_sku', $variant->sku)
            );

        $this->actingAs($director)
            ->get(route('admin.stock-adjustments.show', $adjustment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StockAdjustments/Show')
                ->where('adjustment.product_variant', $expectedLabel)
                ->where('adjustment.product_sku', $variant->sku)
            );
    }

    protected function createVariantWithOptions(): ProductVariant
    {
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Hp ProBook 650 G2',
        ]);

        $product->categories()->attach($category->id);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'HP-650G2-BLK-8GB-I5',
            'quantity' => 10,
        ]);

        $values = collect([
            ['Color', 'Black & Ash'],
            ['Memory', '8gb'],
            ['Processor', 'Intel Core i5'],
            ['Screen Size', '15 Inch'],
            ['OS', 'Windows 11'],
            ['Weight Class', 'Light'],
        ])->map(function (array $definition) {
            $type = VariantType::factory()->create([
                'name' => $definition[0],
            ]);

            return VariantValue::factory()->create([
                'variant_type_id' => $type->id,
                'value' => $definition[1],
            ]);
        });

        $variant->values()->attach($values->pluck('id'));

        return $variant->fresh(['product', 'values.type']);
    }
}
