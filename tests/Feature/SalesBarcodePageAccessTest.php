<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesBarcodePageAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_barcode_page_lists_available_stock_per_variant(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $product = Product::factory()->create(['is_active' => true]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'BARCODE-STOCK-001',
            'quantity' => 12,
            'reserved' => 2,
            'fulfillment_type' => ProductVariant::FULFILLMENT_STOCKED,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'BARCODE-DROP-001',
            'quantity' => 0,
            'reserved' => 0,
            'fulfillment_type' => ProductVariant::FULFILLMENT_DROPSHIPPING,
        ]);

        $this->actingAs($director)
            ->get(route('admin.barcodes.index', ['search' => 'BARCODE-']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InventoryBarcodes')
                ->where('variants.data.0.sku', 'BARCODE-STOCK-001')
                ->where('variants.data.0.available', 10)
                ->where('variants.data.0.is_dropshipping', false)
                ->where('variants.data.1.sku', 'BARCODE-DROP-001')
                ->where('variants.data.1.is_dropshipping', true));
    }

    public function test_sales_representative_can_view_barcode_labels_page(): void
    {
        $rep = User::factory()->create();
        $rep->syncRoles([RoleNames::SALES_REPRESENTATIVE]);

        $this->actingAs($rep)
            ->get(route('sales.barcodes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('InventoryBarcodes'));
    }

    public function test_customer_cannot_view_sales_barcode_labels_page(): void
    {
        $customer = User::factory()->create();
        $customer->syncRoles([RoleNames::CUSTOMER]);

        $this->actingAs($customer)
            ->get(route('sales.barcodes.index'))
            ->assertForbidden();
    }
}
