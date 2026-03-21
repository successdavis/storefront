<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_directors_are_redirected_to_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([RoleNames::DIRECTOR]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_sales_reps_are_redirected_to_sales_dashboard(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([RoleNames::SALES_REPRESENTATIVE]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('sales.dashboard'));
    }
}
