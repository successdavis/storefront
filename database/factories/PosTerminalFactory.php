<?php

namespace Database\Factories;

use App\Models\PosTerminal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PosTerminal>
 */
class PosTerminalFactory extends Factory
{
    protected $model = PosTerminal::class;

    public function definition(): array
    {
        return [
            'name' => 'POS-' . fake()->unique()->numberBetween(1, 100),
            'location' => fake()->address(),
        ];
    }
}
