<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalePayment>
 */
class SalePaymentFactory extends Factory
{
    protected $model = SalePayment::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'method' => fake()->randomElement(['cash', 'card', 'transfer']),
            'amount' => fake()->randomFloat(2, 50, 1000),
        ];
    }
}
