<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        // Use an existing variant when possible
        $variant = ProductVariant::query()->inRandomOrder()->first()
            ?? ProductVariant::factory()->create();

        $qty = $this->faker->numberBetween(1, 5);
        $now = now();

        $unitPrice = ($variant->sale_price
            && (is_null($variant->sale_starts_at) || $variant->sale_starts_at <= $now)
            && (is_null($variant->sale_ends_at)   || $variant->sale_ends_at   >= $now))
            ? $variant->sale_price
            : $variant->regular_price;

        return [
            'sale_id'    => Sale::factory(),
            'variant_id' => $variant->id,   // rename to 'product_variant_id' if that is your column
            'quantity'   => $qty,
            'price'      => round($unitPrice, 2), // unit price at time of sale
        ];
    }

    public function forSale(Sale $sale): self
    {
        return $this->state(fn () => ['sale_id' => $sale->id]);
    }

    public function forVariant(ProductVariant $variant): self
    {
        $now = now();
        $unitPrice = ($variant->sale_price
            && (is_null($variant->sale_starts_at) || $variant->sale_starts_at <= $now)
            && (is_null($variant->sale_ends_at)   || $variant->sale_ends_at   >= $now))
            ? $variant->sale_price
            : $variant->regular_price;

        return $this->state(fn () => [
            'variant_id' => $variant->id,   // or 'product_variant_id'
            'price'      => round($unitPrice, 2),
        ]);
    }

    public function quantity(int $qty): self
    {
        return $this->state(fn () => ['quantity' => $qty]);
    }
}
