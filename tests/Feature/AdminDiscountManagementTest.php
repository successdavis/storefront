<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDiscountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_create_and_toggle_an_automatic_discount(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $category = Category::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($director)->post(route('admin.discounts.store'), [
            'name' => 'Spring Launch Markdown',
            'description' => 'Automatic catalog promotion',
            'type' => Discount::TYPE_PERCENTAGE,
            'application_method' => Discount::APPLICATION_LINE_ITEM,
            'value' => 12.5,
            'customer_scope' => Discount::CUSTOMER_SCOPE_ALL,
            'priority' => 50,
            'is_active' => true,
            'category_ids' => [$category->id],
            'product_ids' => [$product->id],
        ]);

        $discount = Discount::query()->firstOrFail();

        $response->assertRedirect(route('admin.discounts.edit', $discount));

        $this->assertDatabaseHas('discounts', [
            'id' => $discount->id,
            'name' => 'Spring Launch Markdown',
            'code' => null,
            'application_method' => Discount::APPLICATION_LINE_ITEM,
            'type' => Discount::TYPE_PERCENTAGE,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('discount_category', [
            'discount_id' => $discount->id,
            'category_id' => $category->id,
        ]);
        $this->assertDatabaseHas('discount_product', [
            'discount_id' => $discount->id,
            'product_id' => $product->id,
        ]);

        $this->actingAs($director)
            ->patch(route('admin.discounts.toggle-status', $discount))
            ->assertRedirect();

        $this->assertDatabaseHas('discounts', [
            'id' => $discount->id,
            'is_active' => false,
        ]);
    }

    public function test_director_can_create_a_coupon_and_customers_cannot_access_admin_discount_pages(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $customer = User::factory()->create();
        $customer->syncRoles([RoleNames::CUSTOMER]);

        $this->actingAs($customer)
            ->get(route('admin.discounts.index'))
            ->assertForbidden();

        $this->actingAs($director)->post(route('admin.coupons.store'), [
            'name' => 'SAVE15',
            'description' => 'Coupon for checkout',
            'code' => 'SAVE15',
            'type' => Discount::TYPE_PERCENTAGE,
            'value' => 15,
            'customer_scope' => Discount::CUSTOMER_SCOPE_ALL,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('discounts', [
            'code' => 'SAVE15',
            'application_method' => Discount::APPLICATION_ORDER_TOTAL,
            'type' => Discount::TYPE_PERCENTAGE,
        ]);
    }
}
