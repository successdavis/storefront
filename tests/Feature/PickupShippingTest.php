<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Lga;
use App\Models\PickupLocation;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\State;
use App\Models\User;
use App\Services\Shipping\ShippingCostService;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickupShippingTest extends TestCase
{
    use RefreshDatabase;

    protected ShippingMethod $pickupMethod;

    protected State $state;

    protected Lga $ikom;

    protected Lga $obudu;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::query()->create(['name' => 'Nigeria', 'code' => 'NG']);
        $this->state = State::query()->create(['name' => 'Cross River', 'country_id' => $country->id]);
        $this->ikom = Lga::query()->create(['name' => 'Ikom', 'state_id' => $this->state->id]);
        $this->obudu = Lga::query()->create(['name' => 'Obudu', 'state_id' => $this->state->id]);

        $this->pickupMethod = ShippingMethod::query()->create([
            'name' => 'Store Pickup',
            'method_type' => ShippingMethod::TYPE_PICKUP,
            'is_active' => true,
        ]);
    }

    protected function makeLocation(string $name, ?int $lgaId = null): PickupLocation
    {
        return PickupLocation::query()->create([
            'name' => $name,
            'shipping_method_id' => $this->pickupMethod->id,
            'state_id' => $this->state->id,
            'lga_id' => $lgaId,
            'address_line1' => '1 Main Street',
            'is_active' => true,
        ]);
    }

    protected function calculate(?int $pickupLocationId, float $subtotal = 1000.0): array
    {
        return app(ShippingCostService::class)->calculate([
            'shipping_method_id' => $this->pickupMethod->id,
            'state_id' => $this->state->id,
            'pickup_location_id' => $pickupLocationId,
            'subtotal' => $subtotal,
        ]);
    }

    public function test_pickup_without_configured_rate_is_free(): void
    {
        $location = $this->makeLocation('Ikom Store', $this->ikom->id);

        $result = $this->calculate($location->id);

        $this->assertSame(0.0, $result['total']);
        $this->assertNull($result['rate_id']);
    }

    public function test_location_scoped_pickup_rate_charges_its_fee(): void
    {
        $ikomStore = $this->makeLocation('Ikom Store', $this->ikom->id);
        $obuduPoint = $this->makeLocation('Obudu Pickup Point', $this->obudu->id);

        ShippingRate::query()->create([
            'shipping_method_id' => $this->pickupMethod->id,
            'pickup_location_id' => $obuduPoint->id,
            'rate_type' => 'flat',
            'base_rate' => 1500,
            'per_kg' => 0,
            'surcharge' => 0,
            'currency' => 'NGN',
            'is_active' => true,
        ]);

        $obudu = $this->calculate($obuduPoint->id);
        $ikom = $this->calculate($ikomStore->id);

        $this->assertSame(1500.0, $obudu['total']);
        $this->assertSame('pickup', $obudu['method_type']);
        $this->assertSame(0.0, $ikom['total'], 'Locations without their own rate stay free');
    }

    public function test_location_specific_rate_overrides_global_pickup_rate(): void
    {
        $obuduPoint = $this->makeLocation('Obudu Pickup Point', $this->obudu->id);

        ShippingRate::query()->create([
            'shipping_method_id' => $this->pickupMethod->id,
            'rate_type' => 'flat',
            'base_rate' => 500,
            'per_kg' => 0,
            'currency' => 'NGN',
            'is_active' => true,
        ]);

        ShippingRate::query()->create([
            'shipping_method_id' => $this->pickupMethod->id,
            'pickup_location_id' => $obuduPoint->id,
            'rate_type' => 'flat',
            'base_rate' => 1500,
            'per_kg' => 0,
            'currency' => 'NGN',
            'is_active' => true,
        ]);

        $this->assertSame(1500.0, $this->calculate($obuduPoint->id)['total']);
    }

    public function test_global_pickup_rate_applies_when_no_location_rate_exists(): void
    {
        $location = $this->makeLocation('Ikom Store', $this->ikom->id);

        ShippingRate::query()->create([
            'shipping_method_id' => $this->pickupMethod->id,
            'rate_type' => 'flat',
            'base_rate' => 500,
            'per_kg' => 0,
            'surcharge' => 100,
            'currency' => 'NGN',
            'is_active' => true,
        ]);

        $result = $this->calculate($location->id);

        $this->assertSame(600.0, $result['total']);
    }

    public function test_pickup_free_shipping_threshold_waives_the_fee(): void
    {
        $location = $this->makeLocation('Obudu Pickup Point', $this->obudu->id);

        ShippingRate::query()->create([
            'shipping_method_id' => $this->pickupMethod->id,
            'pickup_location_id' => $location->id,
            'rate_type' => 'flat',
            'base_rate' => 1500,
            'per_kg' => 0,
            'free_shipping_threshold' => 50000,
            'currency' => 'NGN',
            'is_active' => true,
        ]);

        $this->assertSame(1500.0, $this->calculate($location->id, 20000)['total']);
        $this->assertSame(0.0, $this->calculate($location->id, 60000)['total']);
        $this->assertTrue($this->calculate($location->id, 60000)['free_shipping_applied']);
    }

    public function test_admin_can_create_pickup_location_and_it_lists_for_the_state(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $this->actingAs($director)
            ->post(route('admin.pickup-locations.store'), [
                'name' => 'Obudu Pickup Point',
                'shipping_method_id' => $this->pickupMethod->id,
                'state_id' => $this->state->id,
                'lga_id' => $this->obudu->id,
                'address_line1' => '5 Ranch Road',
                'phone' => '08030000000',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pickup_locations', [
            'name' => 'Obudu Pickup Point',
            'state_id' => $this->state->id,
            'lga_id' => $this->obudu->id,
        ]);

        $listed = app(ShippingCostService::class)->listPickupLocationsForState($this->state->id);
        $this->assertTrue($listed->pluck('name')->contains('Obudu Pickup Point'));

        $checkout = $this->actingAs($director)
            ->get(route('shipping.locations.pickups', $this->state->id))
            ->assertOk();

        $this->assertSame('Obudu Pickup Point', $checkout->json()[0]['name'] ?? null);
    }

    public function test_customer_cannot_manage_pickup_locations(): void
    {
        $customer = User::factory()->create();
        $customer->syncRoles([RoleNames::CUSTOMER]);

        $this->actingAs($customer)
            ->get(route('admin.pickup-locations.index'))
            ->assertForbidden();
    }
}
