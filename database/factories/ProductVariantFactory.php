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
        $regular = $this->faker->randomFloat(2, 10000, 800000);
        $cost    = round($regular * $this->faker->randomFloat(2, 0.55, 0.9), 2);

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

            'weight'         => $weight,     // kg
            'length'         => $length,     // cm
            'width'          => $width,      // cm
            'height'         => $height,     // cm
            'is_active'      => true,
            'replenishment_status' => ProductVariant::REPLENISHMENT_REORDERABLE,
        ];
    }

    // Convenience states

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
