<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CustomerLocationResolver;
use App\Services\DeliveryEstimateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeliveryEstimateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_uses_rate_timing_fields_to_build_a_delivery_window(): void
    {
        Carbon::setTestNow('2026-03-30 09:00:00');

        [$countryId, $lagosStateId, $lagosLgaId] = $this->createLocationHierarchy('Lagos State', 'Ikeja');
        $zone = $this->createZone('Lagos Zone', $lagosStateId);
        $method = $this->createDeliveryMethod();

        ShippingRate::query()->create([
            'shipping_method_id' => $method->id,
            'shipping_zone_id' => $zone->id,
            'rate_type' => 'flat',
            'base_rate' => 1500,
            'per_kg' => 0,
            'surcharge' => 0,
            'currency' => 'NGN',
            'processing_days_min' => 1,
            'processing_days_max' => 1,
            'transit_days_min' => 1,
            'transit_days_max' => 2,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $variant = $this->createVariantWithWarehouseStock($countryId, $lagosStateId, $lagosLgaId, 'Ikeja Warehouse');

        $estimate = app(DeliveryEstimateService::class)->estimateForVariantId($variant->id, [
            'country_id' => $countryId,
            'state_id' => $lagosStateId,
            'lga_id' => $lagosLgaId,
            'destination_label' => 'Ikeja',
        ], [
            'scope' => 'storefront',
        ]);

        $this->assertTrue($estimate['available']);
        $this->assertSame('2026-04-01', $estimate['earliest_date']);
        $this->assertSame('2026-04-02', $estimate['latest_date']);
        $this->assertSame('Deliver to Ikeja 1–2 Apr', $estimate['storefront_message']);
    }

    public function test_cutoff_and_business_days_push_delivery_to_the_next_working_day(): void
    {
        Carbon::setTestNow('2026-04-03 16:30:00');

        [$countryId, $lagosStateId, $lagosLgaId] = $this->createLocationHierarchy('Lagos State', 'Ikeja');
        $zone = $this->createZone('Lagos Zone', $lagosStateId);
        $method = $this->createDeliveryMethod();

        ShippingRate::query()->create([
            'shipping_method_id' => $method->id,
            'shipping_zone_id' => $zone->id,
            'rate_type' => 'flat',
            'base_rate' => 1500,
            'per_kg' => 0,
            'surcharge' => 0,
            'currency' => 'NGN',
            'processing_days_min' => 0,
            'processing_days_max' => 0,
            'transit_days_min' => 1,
            'transit_days_max' => 1,
            'cutoff_time' => '15:00:00',
            'business_days_only' => true,
            'supports_weekend_delivery' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $variant = $this->createVariantWithWarehouseStock($countryId, $lagosStateId, $lagosLgaId, 'Ikeja Warehouse');

        $estimate = app(DeliveryEstimateService::class)->estimateForVariantId($variant->id, [
            'country_id' => $countryId,
            'state_id' => $lagosStateId,
            'lga_id' => $lagosLgaId,
            'destination_label' => 'Ikeja',
        ], [
            'scope' => 'checkout',
            'shipping_method_id' => $method->id,
            'subtotal' => 100000,
        ]);

        $this->assertTrue($estimate['available']);
        $this->assertSame('2026-04-07', $estimate['earliest_date']);
        $this->assertSame('2026-04-07', $estimate['latest_date']);
        $this->assertSame('Delivery: 7 Apr', $estimate['checkout_message']);
    }

    public function test_checkout_destination_override_changes_the_estimate(): void
    {
        Carbon::setTestNow('2026-03-30 09:00:00');

        [$countryId, $lagosStateId] = $this->createLocationHierarchy('Lagos State', 'Ikeja');
        [, $abujaStateId] = $this->createLocationHierarchy('FCT', 'Municipal Area Council');
        $lagosZone = $this->createZone('Lagos Zone', $lagosStateId);
        $abujaZone = $this->createZone('North Central', $abujaStateId);
        $method = $this->createDeliveryMethod();

        ShippingRate::query()->create([
            'shipping_method_id' => $method->id,
            'shipping_zone_id' => $lagosZone->id,
            'rate_type' => 'flat',
            'base_rate' => 1500,
            'currency' => 'NGN',
            'processing_days_min' => 0,
            'processing_days_max' => 0,
            'transit_days_min' => 1,
            'transit_days_max' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ShippingRate::query()->create([
            'shipping_method_id' => $method->id,
            'shipping_zone_id' => $abujaZone->id,
            'rate_type' => 'flat',
            'base_rate' => 2500,
            'currency' => 'NGN',
            'processing_days_min' => 1,
            'processing_days_max' => 1,
            'transit_days_min' => 3,
            'transit_days_max' => 4,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $variant = ProductVariant::factory()->for(Product::factory())->create([
            'regular_price' => 100000,
            'quantity' => 10,
            'reserved' => 0,
            'average_cost' => 50000,
            'total_cost_on_hand' => 500000,
            'weight' => 1,
            'is_active' => true,
        ]);

        $service = app(DeliveryEstimateService::class);

        $lagosEstimate = $service->estimateForCheckoutItems([
            ['variant_id' => $variant->id, 'quantity' => 1],
        ], [
            'country_id' => $countryId,
            'state_id' => $lagosStateId,
            'destination_label' => 'Lagos',
        ], [
            'scope' => 'checkout',
            'shipping_method_id' => $method->id,
            'subtotal' => 100000,
        ]);

        $abujaEstimate = $service->estimateForCheckoutItems([
            ['variant_id' => $variant->id, 'quantity' => 1],
        ], [
            'country_id' => $countryId,
            'state_id' => $abujaStateId,
            'destination_label' => 'Abuja',
        ], [
            'scope' => 'checkout',
            'shipping_method_id' => $method->id,
            'subtotal' => 100000,
        ]);

        $this->assertTrue($lagosEstimate['available']);
        $this->assertTrue($abujaEstimate['available']);
        $this->assertNotSame($lagosEstimate['checkout_message'], $abujaEstimate['checkout_message']);
        $this->assertSame('Delivery: Tomorrow', $lagosEstimate['checkout_message']);
        $this->assertSame('Delivery: 3–6 Apr', $abujaEstimate['checkout_message']);
    }

    public function test_it_prefers_a_nearer_warehouse_when_multiple_locations_can_fulfil(): void
    {
        Carbon::setTestNow('2026-03-30 09:00:00');

        [$countryId, $lagosStateId, $lagosLgaId] = $this->createLocationHierarchy('Lagos State', 'Ikeja');
        [, $abujaStateId, $abujaLgaId] = $this->createLocationHierarchy('FCT', 'Municipal Area Council');
        $zone = $this->createZone('Lagos Zone', $lagosStateId);
        $this->createZone('North Central', $abujaStateId);
        $method = $this->createDeliveryMethod();

        ShippingRate::query()->create([
            'shipping_method_id' => $method->id,
            'shipping_zone_id' => $zone->id,
            'rate_type' => 'flat',
            'base_rate' => 1500,
            'currency' => 'NGN',
            'processing_days_min' => 0,
            'processing_days_max' => 0,
            'transit_days_min' => 1,
            'transit_days_max' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create([
            'regular_price' => 100000,
            'quantity' => 10,
            'reserved' => 0,
            'average_cost' => 50000,
            'total_cost_on_hand' => 500000,
            'weight' => 1,
            'is_active' => true,
        ]);

        $lagosWarehouse = Warehouse::query()->create([
            'name' => 'Ikeja Warehouse',
            'code' => 'WH-LAG',
            'address' => '12 Allen Avenue',
            'country_id' => $countryId,
            'state_id' => $lagosStateId,
            'lga_id' => $lagosLgaId,
            'active' => true,
        ]);

        $abujaWarehouse = Warehouse::query()->create([
            'name' => 'Abuja Warehouse',
            'code' => 'WH-ABJ',
            'address' => '10 Central Area',
            'country_id' => $countryId,
            'state_id' => $abujaStateId,
            'lga_id' => $abujaLgaId,
            'active' => true,
        ]);

        DB::table('stock_entries')->insert([
            [
                'warehouse_id' => $lagosWarehouse->id,
                'variant_id' => $variant->id,
                'quantity' => 4,
                'unit_cost' => 50000,
                'type' => 'stock_in',
                'effective_at' => now(),
                'track_inventory' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'warehouse_id' => $abujaWarehouse->id,
                'variant_id' => $variant->id,
                'quantity' => 8,
                'unit_cost' => 50000,
                'type' => 'stock_in',
                'effective_at' => now(),
                'track_inventory' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $estimate = app(DeliveryEstimateService::class)->estimateForVariantId($variant->id, [
            'country_id' => $countryId,
            'state_id' => $lagosStateId,
            'lga_id' => $lagosLgaId,
            'destination_label' => 'Ikeja',
        ], [
            'scope' => 'storefront',
        ]);

        $this->assertTrue($estimate['available']);
        $this->assertSame('Ikeja Warehouse', data_get($estimate, 'warehouse.name'));
    }

    public function test_storefront_estimates_try_later_delivery_methods_when_the_first_one_has_no_matching_rate(): void
    {
        Carbon::setTestNow('2026-03-30 09:00:00');

        [$countryId, $crossRiverStateId, $obuduLgaId] = $this->createLocationHierarchy('Cross River State', 'Obudu');
        $zone = $this->createZone('South South', $crossRiverStateId);

        $firstMethod = ShippingMethod::query()->create([
            'name' => 'Express Delivery',
            'description' => 'Faster delivery for covered zones',
            'method_type' => ShippingMethod::TYPE_DELIVERY,
            'sort_order' => 1,
            'business_days_only' => true,
            'supports_weekend_delivery' => false,
            'is_active' => true,
        ]);

        $secondMethod = ShippingMethod::query()->create([
            'name' => 'Standard Delivery',
            'description' => 'Reliable nationwide delivery',
            'method_type' => ShippingMethod::TYPE_DELIVERY,
            'sort_order' => 2,
            'business_days_only' => true,
            'supports_weekend_delivery' => false,
            'is_active' => true,
        ]);

        ShippingRate::query()->create([
            'shipping_method_id' => $secondMethod->id,
            'shipping_zone_id' => $zone->id,
            'rate_type' => 'flat',
            'base_rate' => 2500,
            'currency' => 'NGN',
            'processing_days_min' => 1,
            'processing_days_max' => 1,
            'transit_days_min' => 2,
            'transit_days_max' => 3,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $variant = $this->createVariantWithWarehouseStock($countryId, $crossRiverStateId, $obuduLgaId, 'Obudu Warehouse');

        $estimate = app(DeliveryEstimateService::class)->estimateForVariantId($variant->id, [
            'country_id' => $countryId,
            'state_id' => $crossRiverStateId,
            'lga_id' => $obuduLgaId,
            'destination_label' => 'Obudu',
        ], [
            'scope' => 'storefront',
        ]);

        $this->assertTrue($estimate['available']);
        $this->assertSame($secondMethod->id, data_get($estimate, 'method.id'));
        $this->assertSame('Standard Delivery', data_get($estimate, 'method.name'));
    }

    public function test_customer_location_resolver_stores_browser_location_from_coordinates(): void
    {
        [$countryId, $lagosStateId, $lagosLgaId] = $this->createLocationHierarchy('Lagos State', 'Ikeja');
        DB::table('lgas')->where('id', $lagosLgaId)->update([
            'latitude' => 6.6018,
            'longitude' => 3.3515,
        ]);

        $request = Request::create('/store', 'POST');
        $request->setLaravelSession(app('session')->driver('array'));

        $resolver = app(CustomerLocationResolver::class);
        $location = $resolver->storeBrowserLocation($request, 6.6020, 3.3517, 120);

        $this->assertNotNull($location);
        $this->assertSame('browser', $location['source']);
        $this->assertSame('Ikeja', $location['destination_label']);
        $this->assertSame('browser', $resolver->resolveForRequest($request)['source']);
    }

    public function test_customer_location_resolver_can_reverse_geocode_into_existing_state_and_lga(): void
    {
        [, $crossRiverStateId, $obuduLgaId] = $this->createLocationHierarchy('Cross River State', 'Obudu');

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'address' => [
                    'country' => 'Nigeria',
                    'country_code' => 'ng',
                    'state' => 'Cross River State',
                    'county' => 'Obudu',
                    'town' => 'Obudu',
                ],
            ], 200),
        ]);

        $request = Request::create('/store', 'POST');
        $request->setLaravelSession(app('session')->driver('array'));

        $location = app(CustomerLocationResolver::class)->storeBrowserLocation($request, 6.6682, 9.1645, 80);

        $this->assertNotNull($location);
        $this->assertSame('browser', $location['source']);
        $this->assertSame($crossRiverStateId, $location['state_id']);
        $this->assertSame($obuduLgaId, $location['lga_id']);
        $this->assertSame('Obudu', $location['destination_label']);
    }

    public function test_customer_location_resolver_uses_saved_default_address_when_browser_location_is_missing(): void
    {
        [$countryId, $lagosStateId, $lagosLgaId] = $this->createLocationHierarchy('Lagos State', 'Ikeja');
        $user = User::factory()->create();

        DB::table('customer_addresses')->insert([
            'user_id' => $user->id,
            'label' => 'Primary Address',
            'recipient_name' => 'Jane Doe',
            'phone' => '08000000000',
            'email' => 'jane@example.com',
            'line1' => '12 Allen Avenue',
            'line2' => null,
            'country_id' => $countryId,
            'state_id' => $lagosStateId,
            'lga_id' => $lagosLgaId,
            'postal_code' => null,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/store', 'GET');
        $request->setLaravelSession(app('session')->driver('array'));
        $request->setUserResolver(fn () => $user);

        $location = app(CustomerLocationResolver::class)->resolveForRequest($request);

        $this->assertNotNull($location);
        $this->assertSame('saved_address', $location['source']);
        $this->assertSame('Ikeja', $location['destination_label']);
    }

    public function test_customer_location_resolver_falls_back_to_latest_order_shipping_destination(): void
    {
        [$countryId, $lagosStateId, $lagosLgaId] = $this->createLocationHierarchy('Lagos State', 'Ikeja');
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->online()->create();

        $shipment = Shipment::query()->create([
            'shipping_method_id' => null,
            'type' => 'delivery',
            'weight' => 0,
            'cost' => 0,
            'currency' => 'NGN',
            'status' => 'pending',
            'ready_at' => null,
            'shipped_at' => null,
            'delivered_at' => null,
            'shippable_id' => $order->id,
            'shippable_type' => Order::class,
            'shipping_zone_id' => null,
        ]);

        DB::table('addresses')->insert([
            'shipment_id' => $shipment->id,
            'type' => 'shipping',
            'name' => 'Jane Doe',
            'phone' => '08000000000',
            'email' => 'jane@example.com',
            'line1' => '12 Allen Avenue',
            'line2' => null,
            'state_code' => null,
            'postal_code' => null,
            'country_id' => $countryId,
            'state_id' => $lagosStateId,
            'lga_id' => $lagosLgaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/store', 'GET');
        $request->setLaravelSession(app('session')->driver('array'));
        $request->setUserResolver(fn () => $user);

        $location = app(CustomerLocationResolver::class)->resolveForRequest($request);

        $this->assertNotNull($location);
        $this->assertSame('order_history', $location['source']);
        $this->assertSame('Ikeja', $location['destination_label']);
    }

    public function test_customer_location_resolver_returns_null_without_a_reliable_destination(): void
    {
        $request = Request::create('/store', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $request->setLaravelSession(app('session')->driver('array'));

        $location = app(CustomerLocationResolver::class)->resolveForRequest($request);

        $this->assertNull($location);
    }

    public function test_product_delivery_estimate_endpoint_returns_an_estimate_for_the_supplied_browsing_location(): void
    {
        Carbon::setTestNow('2026-03-30 09:00:00');

        [$countryId, $crossRiverStateId, $obuduLgaId] = $this->createLocationHierarchy('Cross River State', 'Obudu');
        $zone = $this->createZone('South South', $crossRiverStateId);
        $method = $this->createDeliveryMethod();

        ShippingRate::query()->create([
            'shipping_method_id' => $method->id,
            'shipping_zone_id' => $zone->id,
            'rate_type' => 'flat',
            'base_rate' => 2500,
            'currency' => 'NGN',
            'processing_days_min' => 1,
            'processing_days_max' => 1,
            'transit_days_min' => 2,
            'transit_days_max' => 3,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $variant = $this->createVariantWithWarehouseStock($countryId, $crossRiverStateId, $obuduLgaId, 'Obudu Warehouse');
        $product = $variant->product()->firstOrFail();

        if (!$product->slug) {
            $product->forceFill(['slug' => 'product-'.$product->id])->save();
        }

        $response = $this->postJson(route('store.product.delivery-estimate', $product), [
            'variant_id' => $variant->id,
            'destination' => [
                'country_id' => $countryId,
                'state_id' => $crossRiverStateId,
                'lga_id' => $obuduLgaId,
                'destination_label' => 'Obudu',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('delivery_estimate.available', true)
            ->assertJsonPath('delivery_estimate.destination_label', 'Obudu');
    }

    protected function createDeliveryMethod(): ShippingMethod
    {
        return ShippingMethod::query()->create([
            'name' => 'Standard Delivery',
            'description' => 'Reliable doorstep delivery',
            'method_type' => ShippingMethod::TYPE_DELIVERY,
            'sort_order' => 1,
            'business_days_only' => true,
            'supports_weekend_delivery' => false,
            'is_active' => true,
        ]);
    }

    protected function createZone(string $name, int $stateId): ShippingZone
    {
        $zone = ShippingZone::query()->create(['name' => $name]);

        DB::table('shipping_zone_states')->insert([
            'shipping_zone_id' => $zone->id,
            'state_id' => $stateId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $zone;
    }

    protected function createVariantWithWarehouseStock(int $countryId, int $stateId, int $lgaId, string $warehouseName): ProductVariant
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create([
            'regular_price' => 100000,
            'quantity' => 10,
            'reserved' => 0,
            'average_cost' => 50000,
            'total_cost_on_hand' => 500000,
            'weight' => 1,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::query()->create([
            'name' => $warehouseName,
            'code' => 'WH-'.substr(md5($warehouseName), 0, 6),
            'address' => '12 Allen Avenue',
            'country_id' => $countryId,
            'state_id' => $stateId,
            'lga_id' => $lgaId,
            'active' => true,
        ]);

        DB::table('stock_entries')->insert([
            'warehouse_id' => $warehouse->id,
            'variant_id' => $variant->id,
            'quantity' => 5,
            'unit_cost' => 50000,
            'type' => 'stock_in',
            'effective_at' => now(),
            'track_inventory' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $variant;
    }

    protected function createLocationHierarchy(string $stateName, string $lgaName): array
    {
        $now = now();

        $country = DB::table('countries')->where('iso2', 'NG')->first();
        $countryId = $country?->id ?: DB::table('countries')->insertGetId([
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
            'name' => $stateName,
            'code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $stateName), 0, 3)),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $lgaId = DB::table('lgas')->insertGetId([
            'state_id' => $stateId,
            'name' => $lgaName,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$countryId, $stateId, $lgaId];
    }

}
