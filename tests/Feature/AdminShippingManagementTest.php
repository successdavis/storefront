<?php

namespace Tests\Feature;

use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminShippingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_create_and_toggle_shipping_methods_and_rates(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        [$countryId, $stateId] = $this->createLocationHierarchy();

        $zone = ShippingZone::query()->create(['name' => 'Lagos Zone']);
        DB::table('shipping_zone_states')->insert([
            'shipping_zone_id' => $zone->id,
            'state_id' => $stateId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $methodResponse = $this->actingAs($director)->post(route('admin.shipping-methods.store'), [
            'name' => 'Same Day Delivery',
            'description' => 'Fast delivery within the same state.',
            'method_type' => ShippingMethod::TYPE_DELIVERY,
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $method = ShippingMethod::query()->firstOrFail();

        $methodResponse->assertRedirect(route('admin.shipping-methods.edit', $method));

        $this->assertDatabaseHas('shipping_methods', [
            'id' => $method->id,
            'name' => 'Same Day Delivery',
            'method_type' => ShippingMethod::TYPE_DELIVERY,
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $rateResponse = $this->actingAs($director)->post(route('admin.shipping-rates.store'), [
            'shipping_method_id' => $method->id,
            'scope_type' => 'state',
            'state_id' => $stateId,
            'rate_type' => 'flat',
            'base_rate' => 2500,
            'per_kg' => 0,
            'surcharge' => 150,
            'free_shipping_threshold' => 50000,
            'estimated_delivery_text' => 'Delivered today',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $rate = ShippingRate::query()->firstOrFail();

        $rateResponse->assertRedirect(route('admin.shipping-rates.edit', $rate));

        $this->assertDatabaseHas('shipping_rates', [
            'id' => $rate->id,
            'shipping_method_id' => $method->id,
            'state_id' => $stateId,
            'shipping_zone_id' => null,
            'rate_type' => 'flat',
            'base_rate' => 2500,
            'surcharge' => 150,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($director)
            ->patch(route('admin.shipping-methods.toggle-status', $method))
            ->assertRedirect();

        $this->actingAs($director)
            ->patch(route('admin.shipping-rates.toggle-status', $rate))
            ->assertRedirect();

        $this->assertDatabaseHas('shipping_methods', [
            'id' => $method->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('shipping_rates', [
            'id' => $rate->id,
            'is_active' => false,
        ]);
    }

    public function test_customers_cannot_access_admin_shipping_management_pages(): void
    {
        $customer = User::factory()->create();
        $customer->syncRoles([RoleNames::CUSTOMER]);

        $this->actingAs($customer)
            ->get(route('admin.shipping-methods.index'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('admin.shipping-rates.index'))
            ->assertForbidden();
    }

    private function createLocationHierarchy(): array
    {
        $now = now();

        $countryId = DB::table('countries')->insertGetId([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
            'currency' => 'NGN',
            'phone_code' => '234',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $stateId = DB::table('states')->insertGetId([
            'country_id' => $countryId,
            'name' => 'Lagos State',
            'code' => 'LA',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$countryId, $stateId];
    }
}
