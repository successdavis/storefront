<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        // Prices
        $regular = $this->faker->randomFloat(2, 10000, 800000);          // NGN
        $cost    = round($regular * $this->faker->randomFloat(2, 0.55, 0.9), 2);

        $onSale  = $this->faker->boolean(30);
        if ($onSale) {
            $saleStart = now()->subDays($this->faker->numberBetween(0, 7));
            $saleEnd   = (clone $saleStart)->addDays($this->faker->numberBetween(3, 14));
            $salePrice = round($regular * $this->faker->randomFloat(2, 0.7, 0.95), 2);
            // guard: never let sale exceed regular
            $salePrice = min($salePrice, $regular - 1);
        } else {
            $saleStart = null;
            $saleEnd   = null;
            $salePrice = null;
        }

        // Weight and dimensions (optional)
        $hasWeight = $this->faker->boolean(40);
        $weight    = $hasWeight ? $this->faker->randomFloat(3, 0.20, 8.50) : null;

        $hasDims = $this->faker->boolean(35);
        $length  = $hasDims ? $this->faker->randomFloat(2, 10, 60) : null;
        $width   = $hasDims ? $this->faker->randomFloat(2, 10, 60) : null;
        $height  = $hasDims ? $this->faker->randomFloat(2, 2,  30) : null;

        return [
            // Let the seeder set product_id via ->for($product) or ['product_id' => $product->id]
            'product_id'     => null,

            'sku'            => Str::upper('SKU-' . $this->faker->bothify('??###') . '-' . Str::random(4)),
            'quantity'       => $this->faker->numberBetween(0, 120),

            // Not unique in schema; make it nullable to avoid exhaustion during big seeds
            'barcode'        => $this->faker->boolean(70) ? $this->faker->ean13() : null,

            'last_purchase_price'     => $cost,
            'regular_price'  => $regular,
            'sale_price'     => $salePrice,
            'sale_starts_at' => $saleStart,
            'sale_ends_at'   => $saleEnd,

            'weight'         => $weight,     // kg
            'length'         => $length,     // cm
            'width'          => $width,      // cm
            'height'         => $height,     // cm
        ];
    }

    // Convenience states

    public function onSale(): self
    {
        return $this->state(function () {
            $regular = $this->faker->randomFloat(2, 20000, 500000);
            $sale    = round($regular * $this->faker->randomFloat(2, 0.7, 0.9), 2);
            $start   = now()->subDays($this->faker->numberBetween(0, 3));
            $end     = (clone $start)->addDays($this->faker->numberBetween(5, 10));

            return [
                'regular_price'  => $regular,
                'sale_price'     => min($sale, $regular - 1),
                'sale_starts_at' => $start,
                'sale_ends_at'   => $end,
            ];
        });
    }

    public function withWeight(): self
    {
        return $this->state(fn () => ['weight' => $this->faker->randomFloat(3, 0.20, 8.50)]);
    }

    public function withDimensions(): self
    {
        return $this->state(fn () => [
            'length' => $this->faker->randomFloat(2, 10, 60),
            'width'  => $this->faker->randomFloat(2, 10, 60),
            'height' => $this->faker->randomFloat(2,  2, 30),
        ]);
    }
}
