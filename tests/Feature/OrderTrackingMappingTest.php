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

class OrderTrackingMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_order_detail_reflects_shipped_progress_mapping(): void
    {
        $customer = User::factory()->create();
        $customer->syncRoles([RoleNames::CUSTOMER]);

        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $order = $this->createPaidOrder($customer);
        app(OrderManagementService::class)->initializeOrderLifecycle($order, $customer->id);
        app(OrderManagementService::class)->performAction($order, OrderManagementService::ACTION_MARK_SHIPPED, [
            'courier_name' => 'Courier NG',
            'tracking_number' => 'SHIP-001',
            'tracking_url' => 'https://example.test/track/SHIP-001',
        ], $director);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Orders/Show')
                ->where('order.fulfillment_status_label', 'Shipped')
                ->where('order.tracker.steps.3.label', 'Shipped')
                ->where('order.tracker.steps.3.status', 'complete')
            );
    }

    private function createPaidOrder(User $customer): Order
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'quantity' => 15,
            'reserved' => 0,
            'average_cost' => 120,
            'total_cost_on_hand' => 2400,
        ]);

        $order = Order::factory()->online()->create([
            'user_id' => $customer->id,
            'status' => 'paid',
            'subtotal' => 200,
            'shipping_total' => 10,
            'tax_total' => 0,
            'discount' => 0,
            'total_amount' => 210,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
            'price' => 200,
        ]);

        Payment::query()->create([
            'payable_type' => Order::class,
            'payable_id' => $order->id,
            'type' => 'inflow',
            'method' => 'card',
            'amount' => 210,
            'currency' => 'NGN',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Shipment::query()->create([
            'shippable_type' => Order::class,
            'shippable_id' => $order->id,
            'type' => 'delivery',
            'cost' => 10,
            'currency' => 'NGN',
            'status' => 'packed',
        ]);

        return $order;
    }
}
