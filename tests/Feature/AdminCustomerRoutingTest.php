<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminCustomerRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_view_customer_profile_by_slug_route(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $customer = User::factory()->create([
            'name' => 'Ada Lovelace',
        ]);
        $customer->syncRoles([RoleNames::CUSTOMER]);

        $customer = $customer->fresh();

        $this->actingAs($director)
            ->get(route('admin.customers.show', $customer->customerRouteKey()))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Customers/Show')
                ->where('customer.customer_slug', $customer->customer_slug)
                ->where('customer.route_key', $customer->customerRouteKey())
            );
    }

    public function test_existing_numeric_customer_urls_still_resolve(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $customer = User::factory()->create([
            'name' => 'Grace Hopper',
        ]);
        $customer->syncRoles([RoleNames::CUSTOMER]);

        $this->actingAs($director)
            ->get('/admin/customers/'.$customer->id)
            ->assertOk();
    }
}
