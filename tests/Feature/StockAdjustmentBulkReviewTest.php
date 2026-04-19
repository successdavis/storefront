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

class StockAdjustmentBulkReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_exposes_inline_review_state_and_bulk_actions(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $variant = $this->createVariantWithOptions();

        $pending = StockAdjustment::query()->create([
            'variant_id' => $variant->id,
            'previous_quantity' => 10,
            'adjusted_quantity' => -2,
            'reason' => 'count_discrepancy',
            'employee_id' => $director->id,
            'adjusted_at' => now(),
            'status' => StockAdjustment::STATUS_PENDING,
        ]);

        StockAdjustment::query()->create([
            'variant_id' => $variant->id,
            'previous_quantity' => 8,
            'adjusted_quantity' => 1,
            'reason' => 'manual_correction',
            'employee_id' => $director->id,
            'adjusted_at' => now()->subMinute(),
            'status' => StockAdjustment::STATUS_APPROVED,
            'approved_by' => $director->id,
            'approved_at' => now()->subMinute(),
        ]);

        $this->actingAs($director)
            ->get(route('admin.stock-adjustments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StockAdjustments/Index')
                ->where('filters.status', '')
                ->where('status_options.1.value', 'pending')
                ->where('status_options.2.value', 'approved')
                ->where('status_options.3.value', 'rejected')
                ->where('bulk_actions.0.value', 'approve')
                ->where('bulk_actions.1.value', 'reject')
                ->where('adjustments.data.0.id', $pending->id)
                ->where('adjustments.data.0.can_review', true)
                ->where('adjustments.data.1.can_review', false)
            );
    }

    public function test_index_can_filter_adjustments_by_status(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $variant = $this->createVariantWithOptions();

        StockAdjustment::query()->create([
            'variant_id' => $variant->id,
            'previous_quantity' => 10,
            'adjusted_quantity' => -2,
            'reason' => 'count_discrepancy',
            'employee_id' => $director->id,
            'adjusted_at' => now(),
            'status' => StockAdjustment::STATUS_PENDING,
        ]);

        $approved = StockAdjustment::query()->create([
            'variant_id' => $variant->id,
            'previous_quantity' => 8,
            'adjusted_quantity' => 1,
            'reason' => 'manual_correction',
            'employee_id' => $director->id,
            'adjusted_at' => now()->subMinute(),
            'status' => StockAdjustment::STATUS_APPROVED,
            'approved_by' => $director->id,
            'approved_at' => now()->subMinute(),
        ]);

        $this->actingAs($director)
            ->get(route('admin.stock-adjustments.index', ['status' => StockAdjustment::STATUS_APPROVED]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StockAdjustments/Index')
                ->where('filters.status', StockAdjustment::STATUS_APPROVED)
                ->has('adjustments.data', 1)
                ->where('adjustments.data.0.id', $approved->id)
                ->where('adjustments.data.0.status', StockAdjustment::STATUS_APPROVED)
            );
    }

    public function test_bulk_review_can_approve_multiple_adjustments_from_index(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $firstVariant = $this->createVariantWithOptions('HP-650G2-BLK-8GB-I5');
        $secondVariant = $this->createVariantWithOptions('HP-650G2-WHT-8GB-I5');

        $first = StockAdjustment::query()->create([
            'variant_id' => $firstVariant->id,
            'previous_quantity' => 10,
            'adjusted_quantity' => -2,
            'reason' => 'count_discrepancy',
            'employee_id' => $director->id,
            'adjusted_at' => now(),
            'status' => StockAdjustment::STATUS_PENDING,
        ]);

        $second = StockAdjustment::query()->create([
            'variant_id' => $secondVariant->id,
            'previous_quantity' => 10,
            'adjusted_quantity' => 3,
            'reason' => 'manual_correction',
            'employee_id' => $director->id,
            'adjusted_at' => now(),
            'status' => StockAdjustment::STATUS_PENDING,
        ]);

        $this->actingAs($director)
            ->post(route('admin.stock-adjustments.bulk-review'), [
                'adjustment_ids' => [$first->id, $second->id],
                'action' => 'approve',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('stock_adjustments', [
            'id' => $first->id,
            'status' => StockAdjustment::STATUS_APPROVED,
            'approved_by' => $director->id,
        ]);

        $this->assertDatabaseHas('stock_adjustments', [
            'id' => $second->id,
            'status' => StockAdjustment::STATUS_APPROVED,
            'approved_by' => $director->id,
        ]);
    }

    protected function createVariantWithOptions(string $sku = 'HP-650G2-BLK-8GB-I5'): ProductVariant
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
            'sku' => $sku,
            'quantity' => 10,
            'last_purchase_price' => 50000,
        ]);

        $values = collect([
            ['Color', str_contains($sku, 'WHT') ? 'White' : 'Black & Ash'],
            ['Memory', '8gb'],
            ['Processor', 'Intel Core i5'],
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
