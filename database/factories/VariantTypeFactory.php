<?php

namespace Database\Factories;

use App\Models\VariantType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VariantType>
 */
class VariantTypeFactory extends Factory
{
    protected $model = VariantType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Size', 'Color', 'Material']),
        ];
    }
}
