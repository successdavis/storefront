<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\User;
use App\Services\OrderManagementService;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_view_orders_and_move_a_paid_order_into_processing(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $order = $this->createPaidOrder();

        $this->actingAs($director)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Orders/Index')
                ->has('orders.data', 1)
            );

        $this->actingAs($director)
            ->patch(route('admin.orders.status', $order), [
                'action' => OrderManagementService::ACTION_MARK_PROCESSING,
                'note' => 'Warehouse picking started.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shipments', [
            'id' => $order->shipment->id,
            'status' => 'processing',
        ]);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status_type' => OrderManagementService::HISTORY_TYPE_FULFILLMENT,
            'new_status' => 'processing',
            'note' => 'Warehouse picking started.',
        ]);
    }

    public function test_director_can_add_internal_notes_to_an_order(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $order = $this->createPaidOrder();

        $this->actingAs($director)
            ->post(route('admin.orders.notes.store', $order), [
                'note' => 'Customer requested a courtesy call before dispatch.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('order_notes', [
            'order_id' => $order->id,
            'user_id' => $director->id,
            'note' => 'Customer requested a courtesy call before dispatch.',
        ]);
    }

    private function createPaidOrder(): Order
    {
        $customer = User::factory()->create();
        $customer->syncRoles([RoleNames::CUSTOMER]);

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'quantity' => 20,
            'reserved' => 0,
            'average_cost' => 150,
            'total_cost_on_hand' => 3000,
        ]);

        $order = Order::factory()->online()->create([
            'user_id' => $customer->id,
            'status' => 'paid',
            'subtotal' => 240,
            'shipping_total' => 10,
            'tax_total' => 0,
            'discount' => 0,
            'total_amount' => 250,
            'currency' => 'NGN',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
            'price' => 120,
        ]);

        Payment::query()->create([
            'payable_type' => Order::class,
            'payable_id' => $order->id,
            'type' => 'inflow',
            'method' => 'card',
            'amount' => 250,
            'currency' => 'NGN',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $shipment = Shipment::query()->create([
            'shippable_type' => Order::class,
            'shippable_id' => $order->id,
            'type' => 'delivery',
            'cost' => 10,
            'currency' => 'NGN',
            'status' => 'pending',
        ]);

        $order->setRelation('shipment', $shipment);

        app(OrderManagementService::class)->initializeOrderLifecycle($order, $customer->id);

        return $order->fresh(['shipment']);
    }
}
