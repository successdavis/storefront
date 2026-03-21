<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
