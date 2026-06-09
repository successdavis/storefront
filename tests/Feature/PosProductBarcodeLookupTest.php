<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosProductBarcodeLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pos_products_api_can_lookup_active_variant_by_exact_barcode(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $matchingProduct = Product::factory()->create(['name' => 'Scanned Power Bank']);
        $matchingVariant = ProductVariant::factory()
            ->for($matchingProduct)
            ->create([
                'barcode' => '2603200066067',
                'sku' => 'SCAN-MATCH',
                'regular_price' => 18000,
                'is_active' => true,
            ]);

        $otherProduct = Product::factory()->create(['name' => 'Other Product']);
        ProductVariant::factory()
            ->for($otherProduct)
            ->create([
                'barcode' => '2603200066068',
                'sku' => 'SCAN-OTHER',
                'is_active' => true,
            ]);

        $this->actingAs($director)
            ->getJson(route('admin.pos.products.api', ['barcode' => '2603200066067']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingVariant->id)
            ->assertJsonPath('data.0.barcode', '2603200066067')
            ->assertJsonPath('data.0.product.name', 'Scanned Power Bank');
    }

    public function test_admin_pos_products_api_does_not_return_inactive_variant_for_barcode_scan(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $product = Product::factory()->create(['name' => 'Inactive Barcode Product']);
        ProductVariant::factory()
            ->for($product)
            ->create([
                'barcode' => '2603200066067',
                'is_active' => false,
            ]);

        $this->actingAs($director)
            ->getJson(route('admin.pos.products.api', ['barcode' => '2603200066067']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
