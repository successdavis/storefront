<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderShippedNotification;
use App\Services\OrderManagementService;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderLifecycleNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_confirmation_notification_is_sent_when_lifecycle_is_initialized(): void
    {
        Notification::fake();

        $order = $this->createPaidOrder();

        DB::transaction(function () use ($order) {
            app(OrderManagementService::class)->initializeOrderLifecycle($order, $order->user_id);
        });

        Notification::assertSentTo($order->user, OrderPlacedNotification::class);
    }

    public function test_shipment_notification_is_sent_when_order_is_marked_shipped(): void
    {
        Notification::fake();

        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $order = $this->createPaidOrder();
        DB::transaction(function () use ($order) {
            app(OrderManagementService::class)->initializeOrderLifecycle($order, $order->user_id);
        });

        app(OrderManagementService::class)->performAction($order, OrderManagementService::ACTION_MARK_SHIPPED, [
            'courier_name' => 'DHL',
            'tracking_number' => 'TRACK-123',
            'tracking_url' => 'https://example.test/track/TRACK-123',
            'note' => 'Dispatched from main warehouse.',
        ], $director);

        Notification::assertSentTo($order->user, OrderShippedNotification::class);
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
            'status' => 'packed',
        ]);

        return $order->fresh(['user', 'shipment']);
    }
}
