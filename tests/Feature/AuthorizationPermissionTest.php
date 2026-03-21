<?php

namespace Tests\Feature;

use App\Models\PosTerminal;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthorizationPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_can_access_their_account_dashboard(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([RoleNames::CUSTOMER]);

        $this->actingAs($user)
            ->get(route('account.dashboard'))
            ->assertOk();
    }

    public function test_sales_reps_cannot_access_customer_account_dashboard(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([RoleNames::SALES_REPRESENTATIVE]);

        $this->actingAs($user)
            ->get(route('account.dashboard'))
            ->assertForbidden();
    }

    public function test_sales_reps_can_access_sales_dashboard(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([RoleNames::SALES_REPRESENTATIVE]);

        $this->actingAs($user)
            ->get(route('sales.dashboard'))
            ->assertOk();
    }

    public function test_customers_cannot_access_sales_dashboard(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([RoleNames::CUSTOMER]);

        $this->actingAs($user)
            ->get(route('sales.dashboard'))
            ->assertForbidden();
    }

    public function test_sales_reps_are_redirected_to_the_sales_terminal_selector_for_pos(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([RoleNames::SALES_REPRESENTATIVE]);

        [$countryId, $stateId, $lgaId] = $this->createLocationHierarchy();

        $warehouse = Warehouse::query()->create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'country_id' => $countryId,
            'state_id' => $stateId,
            'lga_id' => $lgaId,
            'active' => true,
        ]);

        $user->warehouses()->attach($warehouse->id);

        PosTerminal::query()->create([
            'name' => 'Front POS',
            'location' => 'Front Desk',
            'warehouse_id' => $warehouse->id,
        ]);

        Setting::set('use_pos_terminal_password', 'true');

        $this->actingAs($user)
            ->get(route('sales.pos.index'))
            ->assertRedirect(route('sales.pos.selectTerminal'));
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
