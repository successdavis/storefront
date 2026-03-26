<?php

namespace Tests\Feature;

use App\Models\Admin\VariantImage;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockEntry;
use App\Models\VariantType;
use App\Models\VariantValue;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductVariantReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_existing_variant_does_not_create_duplicate_opening_balance_history(): void
    {
        $product = Product::factory()->create();
        $color = VariantType::factory()->create(['name' => 'Color']);
        $size = VariantType::factory()->create(['name' => 'Size']);

        $black = VariantValue::factory()->create(['variant_type_id' => $color->id, 'value' => 'Black']);
        $large = VariantValue::factory()->create(['variant_type_id' => $size->id, 'value' => 'L']);

        $variant = ProductVariant::factory()->for($product)->create([
            'quantity' => 5,
            'regular_price' => 250000,
            'last_purchase_price' => 150000,
            'is_active' => true,
        ]);
        $variant->values()->sync([$black->id, $large->id]);

        StockEntry::query()->create([
            'variant_id' => $variant->id,
            'quantity' => 5,
            'unit_cost' => 150000,
            'type' => 'stock_in',
            'effective_at' => now(),
            'reason' => 'Initial receipt',
        ]);

        app(ProductService::class)->update($product, [
            'name' => 'Updated Product Name',
            'variants' => [
                $this->payloadFor($variant, [
                    'regular_price' => 275000,
                    'value_ids' => [$black->id, $large->id],
                ]),
            ],
        ]);

        $variant->refresh();

        $this->assertSame(5, $variant->quantity);
        $this->assertSame(275000.0, (float) $variant->regular_price);
        $this->assertDatabaseCount('stock_entries', 1);
        $this->assertDatabaseCount('opening_balance_items', 0);
    }

    public function test_it_preserves_variant_identity_for_a_one_to_one_attribute_extension(): void
    {
        $product = Product::factory()->create();
        $color = VariantType::factory()->create(['name' => 'Color']);
        $size = VariantType::factory()->create(['name' => 'Size']);
        $display = VariantType::factory()->create(['name' => 'Display']);

        $black = VariantValue::factory()->create(['variant_type_id' => $color->id, 'value' => 'Black']);
        $white = VariantValue::factory()->create(['variant_type_id' => $color->id, 'value' => 'White']);
        $large = VariantValue::factory()->create(['variant_type_id' => $size->id, 'value' => 'L']);
        $display15 = VariantValue::factory()->create(['variant_type_id' => $display->id, 'value' => '15 inch']);

        $blackVariant = ProductVariant::factory()->for($product)->create([
            'quantity' => 4,
            'regular_price' => 200000,
            'last_purchase_price' => 120000,
            'is_active' => true,
        ]);
        $blackVariant->values()->sync([$black->id, $large->id]);

        $whiteVariant = ProductVariant::factory()->for($product)->create([
            'quantity' => 6,
            'regular_price' => 210000,
            'last_purchase_price' => 125000,
            'is_active' => true,
        ]);
        $whiteVariant->values()->sync([$white->id, $large->id]);

        StockEntry::query()->create([
            'variant_id' => $blackVariant->id,
            'quantity' => 4,
            'unit_cost' => 120000,
            'type' => 'stock_in',
            'effective_at' => now(),
            'reason' => 'Opening stock',
        ]);

        StockEntry::query()->create([
            'variant_id' => $whiteVariant->id,
            'quantity' => 6,
            'unit_cost' => 125000,
            'type' => 'stock_in',
            'effective_at' => now(),
            'reason' => 'Opening stock',
        ]);

        app(ProductService::class)->update($product, [
            'name' => $product->name,
            'variants' => [
                $this->newPayloadFor([$black->id, $large->id, $display15->id], 200000),
                $this->newPayloadFor([$white->id, $large->id, $display15->id], 210000),
            ],
        ]);

        $blackVariant->refresh();
        $whiteVariant->refresh();

        $this->assertTrue($blackVariant->is_active);
        $this->assertTrue($whiteVariant->is_active);
        $this->assertSame(
            collect([$black->id, $display15->id, $large->id])->sort()->values()->all(),
            $blackVariant->values()->pluck('variant_values.id')->sort()->values()->all()
        );
        $this->assertSame(
            collect([$white->id, $display15->id, $large->id])->sort()->values()->all(),
            $whiteVariant->values()->pluck('variant_values.id')->sort()->values()->all()
        );
        $this->assertDatabaseCount('product_variants', 2);
        $this->assertDatabaseCount('stock_entries', 2);
    }

    public function test_it_archives_removed_variants_without_detaching_history_or_images(): void
    {
        $product = Product::factory()->create();
        $color = VariantType::factory()->create(['name' => 'Color']);
        $size = VariantType::factory()->create(['name' => 'Size']);

        $black = VariantValue::factory()->create(['variant_type_id' => $color->id, 'value' => 'Black']);
        $white = VariantValue::factory()->create(['variant_type_id' => $color->id, 'value' => 'White']);
        $large = VariantValue::factory()->create(['variant_type_id' => $size->id, 'value' => 'L']);

        $blackVariant = ProductVariant::factory()->for($product)->create(['regular_price' => 180000, 'is_active' => true]);
        $blackVariant->values()->sync([$black->id, $large->id]);

        $whiteVariant = ProductVariant::factory()->for($product)->create([
            'quantity' => 2,
            'regular_price' => 185000,
            'last_purchase_price' => 100000,
            'is_active' => true,
        ]);
        $whiteVariant->values()->sync([$white->id, $large->id]);

        VariantImage::query()->create([
            'product_variant_id' => $whiteVariant->id,
            'path' => 'variants/test-white.webp',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        StockEntry::query()->create([
            'variant_id' => $whiteVariant->id,
            'quantity' => 2,
            'unit_cost' => 100000,
            'type' => 'stock_in',
            'effective_at' => now(),
            'reason' => 'Opening stock',
        ]);

        app(ProductService::class)->update($product, [
            'name' => $product->name,
            'variants' => [
                $this->payloadFor($blackVariant, [
                    'value_ids' => [$black->id, $large->id],
                ]),
            ],
        ]);

        $whiteVariant->refresh();

        $this->assertFalse($whiteVariant->is_active);
        $this->assertNull($whiteVariant->deleted_at);
        $this->assertSame([$white->id, $large->id], $whiteVariant->values()->pluck('variant_values.id')->sort()->values()->all());
        $this->assertSame(1, $whiteVariant->images()->count());
        $this->assertDatabaseHas('stock_entries', ['variant_id' => $whiteVariant->id]);
    }

    public function test_it_blocks_ambiguous_splits_for_variants_with_history(): void
    {
        $product = Product::factory()->create();
        $color = VariantType::factory()->create(['name' => 'Color']);
        $size = VariantType::factory()->create(['name' => 'Size']);
        $display = VariantType::factory()->create(['name' => 'Display']);

        $black = VariantValue::factory()->create(['variant_type_id' => $color->id, 'value' => 'Black']);
        $large = VariantValue::factory()->create(['variant_type_id' => $size->id, 'value' => 'L']);
        $display15 = VariantValue::factory()->create(['variant_type_id' => $display->id, 'value' => '15 inch']);
        $display16 = VariantValue::factory()->create(['variant_type_id' => $display->id, 'value' => '16 inch']);

        $variant = ProductVariant::factory()->for($product)->create([
            'quantity' => 3,
            'regular_price' => 199000,
            'last_purchase_price' => 120000,
            'is_active' => true,
        ]);
        $variant->values()->sync([$black->id, $large->id]);

        StockEntry::query()->create([
            'variant_id' => $variant->id,
            'quantity' => 3,
            'unit_cost' => 120000,
            'type' => 'stock_in',
            'effective_at' => now(),
            'reason' => 'Opening stock',
        ]);

        try {
            app(ProductService::class)->update($product, [
                'name' => $product->name,
                'variants' => [
                    $this->newPayloadFor([$black->id, $large->id, $display15->id], 199000),
                    $this->newPayloadFor([$black->id, $large->id, $display16->id], 209000),
                ],
            ]);

            $this->fail('Expected a validation exception for the ambiguous split.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        $variant->refresh();

        $this->assertTrue($variant->is_active);
        $this->assertSame([$black->id, $large->id], $variant->values()->pluck('variant_values.id')->sort()->values()->all());
        $this->assertDatabaseCount('product_variants', 1);
    }

    private function payloadFor(ProductVariant $variant, array $overrides = []): array
    {
        return array_merge([
            'id' => $variant->id,
            'archived' => false,
            'sku' => $variant->sku,
            'quantity' => (int) $variant->quantity,
            'barcode' => $variant->barcode,
            'last_purchase_price' => $variant->last_purchase_price,
            'regular_price' => (float) $variant->regular_price,
            'sale_price' => $variant->sale_price,
            'sale_starts_at' => optional($variant->sale_starts_at)?->toIso8601String(),
            'sale_ends_at' => optional($variant->sale_ends_at)?->toIso8601String(),
            'weight' => $variant->weight,
            'length' => $variant->length,
            'width' => $variant->width,
            'height' => $variant->height,
            'value_ids' => $variant->values()->pluck('variant_values.id')->all(),
            'images' => [],
        ], $overrides);
    }

    private function newPayloadFor(array $valueIds, float $regularPrice): array
    {
        return [
            'archived' => false,
            'sku' => '',
            'quantity' => 0,
            'barcode' => '',
            'last_purchase_price' => null,
            'regular_price' => $regularPrice,
            'sale_price' => null,
            'sale_starts_at' => null,
            'sale_ends_at' => null,
            'weight' => null,
            'length' => null,
            'width' => null,
            'height' => null,
            'value_ids' => $valueIds,
            'images' => [],
        ];
    }
}
