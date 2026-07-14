<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesBarcodePageAccessTest extends TestCase
{
    use RefreshDatabase;

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
