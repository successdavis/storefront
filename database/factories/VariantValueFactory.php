<?php

namespace Database\Factories;

use App\Models\VariantType;
use App\Models\VariantValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VariantValue>
 */
class VariantValueFactory extends Factory
{
    protected $model = VariantValue::class;

    public function definition(): array
    {
        return [
            'variant_type_id' => VariantType::factory(),
            'value' => fake()->word(),
        ];
    }
}
