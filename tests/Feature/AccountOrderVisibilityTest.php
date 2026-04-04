<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountOrderVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_only_view_their_own_orders(): void
    {
        $owner = User::factory()->create();
        $owner->syncRoles([RoleNames::CUSTOMER]);

        $otherCustomer = User::factory()->create();
        $otherCustomer->syncRoles([RoleNames::CUSTOMER]);

        $order = $this->createOrderForCustomer($owner);

        $this->actingAs($otherCustomer)
            ->get(route('account.orders.show', $order))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Orders/Show')
                ->where('order.order_number', $order->order_number)
            );
    }

    private function createOrderForCustomer(User $customer): Order
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'reserved' => 0,
        ]);

        $order = Order::factory()->online()->create([
            'user_id' => $customer->id,
            'status' => 'paid',
            'total_amount' => 150,
            'subtotal' => 150,
            'shipping_total' => 0,
            'tax_total' => 0,
            'discount' => 0,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
            'price' => 150,
        ]);

        Payment::query()->create([
            'payable_type' => Order::class,
            'payable_id' => $order->id,
            'type' => 'inflow',
            'method' => 'card',
            'amount' => 150,
            'currency' => 'NGN',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Shipment::query()->create([
            'shippable_type' => Order::class,
            'shippable_id' => $order->id,
            'type' => 'delivery',
            'cost' => 0,
            'currency' => 'NGN',
            'status' => 'pending',
        ]);

        return $order;
    }
}
