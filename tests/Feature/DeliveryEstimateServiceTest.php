<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\Warehouse;
use App\Services\CustomerLocationResolver;
use App\Services\DeliveryEstimateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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
            'sale_price' => null,
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
            'sale_price' => null,
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

    public function test_customer_location_resolver_uses_default_location_for_private_ips(): void
    {
        $request = Request::create('/store', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $request->setLaravelSession(app('session')->driver('array'));

        $location = app(CustomerLocationResolver::class)->resolveForRequest($request);

        $this->assertNotNull($location);
        $this->assertSame('default', $location['source']);
        $this->assertSame('Lagos', $location['destination_label']);
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
            'sale_price' => null,
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
