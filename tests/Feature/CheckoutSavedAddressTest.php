<?php

namespace Tests\Feature;

use App\Models\CustomerAddress;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\CustomerAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckoutSavedAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_hydrates_the_selected_saved_address(): void
    {
        $user = User::factory()->create();
        [$countryId, $stateId, $lgaId] = $this->createLocationHierarchy();

        $address = CustomerAddress::query()->create([
            'user_id' => $user->id,
            'label' => 'Head Office',
            'recipient_name' => 'Ada Customer',
            'phone' => '08030000000',
            'email' => 'ada@example.com',
            'line1' => '42 Marina Road',
            'line2' => 'Suite 8',
            'country_id' => $countryId,
            'state_id' => $stateId,
            'lga_id' => $lgaId,
            'postal_code' => '100001',
            'is_default' => true,
        ]);

        $checkout = app(CheckoutService::class)->getCheckoutData($user, [
            'address_id' => $address->id,
        ]);

        $this->assertSame($address->id, $checkout['selected_shipping']['address_id']);
        $this->assertSame($address->line1, $checkout['selected_shipping']['line1']);
        $this->assertSame($address->line2, $checkout['selected_shipping']['line2']);
        $this->assertSame($address->phone, $checkout['selected_shipping']['phone']);
        $this->assertSame($address->state_id, $checkout['selected_shipping']['state_id']);
        $this->assertSame($address->lga_id, $checkout['selected_shipping']['lga_id']);
        $this->assertCount(1, $checkout['saved_addresses']);
        $this->assertSame($address->id, $checkout['saved_addresses'][0]['id']);
    }

    public function test_checkout_can_save_a_new_delivery_address_for_future_purchases(): void
    {
        $user = User::factory()->create(['name' => 'Ada Customer', 'email' => 'ada@example.com']);
        [$countryId, $stateId, $lgaId] = $this->createLocationHierarchy();

        $savedAddress = app(CustomerAddressService::class)->rememberCheckoutAddress($user, [
            'recipient_name' => 'Ada Customer',
            'email' => 'ada@example.com',
            'phone' => '08030000000',
            'line1' => '42 Marina Road',
            'line2' => 'Suite 8',
            'country_id' => $countryId,
            'state_id' => $stateId,
            'lga_id' => $lgaId,
            'postal_code' => '100001',
        ]);

        $this->assertNotNull($savedAddress);
        $this->assertSame('Primary Address', $savedAddress->label);
        $this->assertSame('42 Marina Road', $savedAddress->line1);
        $this->assertTrue($savedAddress->is_default);
        $this->assertDatabaseHas('customer_addresses', [
            'user_id' => $user->id,
            'line1' => '42 Marina Road',
            'state_id' => $stateId,
            'lga_id' => $lgaId,
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
            'name' => 'Lagos',
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
