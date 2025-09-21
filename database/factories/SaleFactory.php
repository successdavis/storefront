<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PosTerminal;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'pos_terminal_id' => PosTerminal::factory(),
            'user_id' => User::factory(),
            'total_amount' => fake()->randomFloat(2, 100, 1000),
        ];
    }
}
