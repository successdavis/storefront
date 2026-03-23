<?php

namespace Tests\Feature;

use App\Models\PickupLocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\OrderService;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShippingPricingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_preview_prefers_lga_specific_rate_over_state_zone_and_global_fallbacks(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([RoleNames::CUSTOMER]);

        [$countryId, $stateId, $lgaId] = $this->createLocationHierarchy();

        $zone = ShippingZone::query()->create(['name' => 'Lagos Zone']);
        DB::table('shipping_zone_states')->insert([
            'shipping_zone_id' => $zone->id,
            'state_id' => $stateId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $method = ShippingMethod::query()->create([
            'name' => 'Standard Delivery',
            'description' => 'Home delivery',
            'method_type' => ShippingMethod::TYPE_DELIVERY,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        ShippingRate::query()->create([
            'shipping_method_id' => $method->id,
            'shipping_zone_id' => null,
            'state_id' => null,
            'lga_id' => null,
            'rate_type' => 'flat',
            'base_rate' => 4000,
            'per_kg' => 0,
            'surcharge' => 0,
            'currency' => 'NGN',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        ShippingRate::query()->create([
            'shipping_method_id' => $method->id,
            'shipping_zone_id' => $zone->id,
            'state_id' => null,
            'lga_id' => null,
            'rate_type' => 'flat',
            'base_rate' => 3000,
            'per_kg' => 0,
            'surcharge' => 0,
            'currency' => 'NGN',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        ShippingRate::query()->create([
            'shipping_method_id' => $method->id,
            'shipping_zone_id' => null,
            'state_id' => $stateId,
            'lga_id' => null,
            'rate_type' => 'flat',
            'base_rate' => 2500,
            'per_kg' => 0,
            'surcharge' => 0,
            'currency' => 'NGN',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        ShippingRate::query()->create([
            'shipping_method_id' => $method->id,
            'shipping_zone_id' => null,
            'state_id' => $stateId,
            'lga_id' => $lgaId,
            'rate_type' => 'flat',
            'base_rate' => 2000,
            'per_kg' => 0,
            'surcharge' => 0,
            'currency' => 'NGN',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'regular_price' => 100,
            'sale_price' => null,
            'quantity' => 15,
            'reserved' => 0,
            'average_cost' => 50,
            'total_cost_on_hand' => 750,
        ]);

        $response = $this->actingAs($user)->postJson(route('checkout.preview'), [
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 1],
            ],
            'shipping' => [
                'shipping_method_id' => $method->id,
                'state_id' => $stateId,
                'lga_id' => $lgaId,
            ],
            'channel' => 'online',
        ]);

        $response->assertOk()->assertJson([
            'subtotal' => 100,
            'shipping_total' => 2000,
            'total' => 2100,
        ]);
    }

    public function test_pickup_method_returns_zero_shipping_and_creates_pickup_record_when_order_is_finalized(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([RoleNames::CUSTOMER]);

        [$countryId, $stateId, $lgaId] = $this->createLocationHierarchy();

        $zone = ShippingZone::query()->create(['name' => 'Lagos Zone']);
        DB::table('shipping_zone_states')->insert([
            'shipping_zone_id' => $zone->id,
            'state_id' => $stateId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $method = ShippingMethod::query()->create([
            'name' => 'Pickup',
            'description' => 'Collect from store',
            'method_type' => ShippingMethod::TYPE_PICKUP,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $pickupLocation = PickupLocation::query()->create([
            'shipping_method_id' => $method->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'Ikeja Pickup Hub',
            'address_line1' => '12 Allen Avenue',
            'country_id' => $countryId,
            'state_id' => $stateId,
            'lga_id' => $lgaId,
            'phone' => '08000000000',
            'timezone' => 'Africa/Lagos',
            'slot_duration_minutes' => 0,
            'capacity_per_slot' => 0,
            'lead_time_hours' => 0,
            'is_active' => true,
        ]);

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'regular_price' => 120,
            'sale_price' => null,
            'quantity' => 20,
            'reserved' => 0,
            'average_cost' => 60,
            'total_cost_on_hand' => 1200,
        ]);

        $preview = $this->actingAs($user)->postJson(route('checkout.preview'), [
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 1],
            ],
            'shipping' => [
                'shipping_method_id' => $method->id,
                'pickup_location_id' => $pickupLocation->id,
                'state_id' => $stateId,
                'line1' => 'Pickup collection',
                'phone' => '08000000000',
            ],
            'channel' => 'online',
        ]);

        $preview->assertOk()->assertJson([
            'subtotal' => 120,
            'shipping_total' => 0,
            'total' => 120,
        ]);

        $token = (string) $preview->json('checkout_token');

        $order = app(OrderService::class)->handle([
            'customer_id' => $user->id,
            'channel' => 'online',
            'payment_method' => 'card',
            'payment_status' => 'paid',
            'transaction_reference' => 'TEST-PICKUP-REF',
            'checkout_token' => $token,
        ]);

        $this->assertDatabaseHas('shipments', [
            'shippable_type' => 'App\\Models\\Order',
            'shippable_id' => $order->id,
            'shipping_method_id' => $method->id,
            'type' => 'pickup',
            'cost' => 0,
        ]);

        $shipmentId = DB::table('shipments')->where('shippable_id', $order->id)->value('id');

        $this->assertDatabaseHas('pickups', [
            'shipment_id' => $shipmentId,
            'pickup_location_id' => $pickupLocation->id,
        ]);
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

        $lgaId = DB::table('lgas')->insertGetId([
            'state_id' => $stateId,
            'name' => 'Ikeja',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$countryId, $stateId, $lgaId];
    }
}
