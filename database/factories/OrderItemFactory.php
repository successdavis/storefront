<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        // Use an existing variant when possible to avoid stray products
        $variant = ProductVariant::query()->inRandomOrder()->first()
            ?? ProductVariant::factory()->create();

        $qty = $this->faker->numberBetween(1, 10);
        $effectivePrice = $variant->regular_price;

        return [
            'order_id'   => Order::factory(),
            'variant_id' => $variant->id,      // change to 'product_variant_id' if that's your column
            'quantity'   => $qty,
            'price'      => $effectivePrice,   // unit price
        ];
    }

    public function forOrder(Order $order): self
    {
        return $this->state(fn () => ['order_id' => $order->id]);
    }

    public function forVariant(ProductVariant $variant): self
    {
        $effectivePrice = $variant->regular_price;

        return $this->state(fn () => [
            'variant_id' => $variant->id,      // change key name if needed
            'price'      => $effectivePrice,
        ]);
    }

    public function quantity(int $qty): self
    {
        return $this->state(fn () => ['quantity' => $qty]);
    }
}
