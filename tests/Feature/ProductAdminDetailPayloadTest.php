<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAdminDetailPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_detail_payload_includes_pricing_summary_transactions_and_notes(): void
    {
        $product = Product::factory()->create([
            'name' => 'Business Laptop',
        ]);

        $variantA = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'quantity' => 8,
            'reserved' => 2,
            'last_purchase_price' => 150000,
            'average_cost' => 140000,
            'regular_price' => 220000,
        ]);

        $variantB = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'reserved' => 1,
            'last_purchase_price' => 180000,
            'average_cost' => 170000,
            'regular_price' => 260000,
        ]);

        $order = Order::factory()->online()->create([
            'status' => 'paid',
        ]);

        OrderItem::factory()->forOrder($order)->forVariant($variantA)->quantity(2)->create([
            'price' => 220000,
        ]);

        StockAdjustment::query()->create([
            'variant_id' => $variantB->id,
            'previous_quantity' => 5,
            'adjusted_quantity' => -1,
            'reason' => 'cycle_count',
            'employee_id' => User::factory()->create()->id,
            'status' => StockAdjustment::STATUS_PENDING,
            'adjusted_at' => now(),
        ]);

        $service = app(ProductService::class);
        $service->storeAdminNote($product, User::factory()->create(), 'Price checked against supplier quote.');

        $payload = $service->adminDetailPayload($product->fresh());

        $this->assertSame(13, $payload['total_stock']);
        $this->assertSame(10, $payload['available_stock']);
        $this->assertSame(150000.0, $payload['pricing_summary']['cost']['min']);
        $this->assertSame(180000.0, $payload['pricing_summary']['cost']['max']);
        $this->assertSame(220000.0, $payload['pricing_summary']['sale_price']['min']);
        $this->assertSame(260000.0, $payload['pricing_summary']['sale_price']['max']);
        $this->assertTrue(collect($payload['transactions'])->contains(fn ($entry) => $entry['type'] === 'sales_orders'));
        $this->assertTrue(collect($payload['transactions'])->contains(fn ($entry) => $entry['type'] === 'stock_adjustments'));
        $this->assertSame('Price checked against supplier quote.', $payload['notes'][0]['note']);
    }
}
