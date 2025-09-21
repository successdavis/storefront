<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\ProductVariantValue;
use App\Models\VariantValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariantValue>
 */
class ProductVariantValueFactory extends Factory
{
    protected $model = ProductVariantValue::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'variant_value_id' => VariantValue::factory(),
        ];
    }
}
