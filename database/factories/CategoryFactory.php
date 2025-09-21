<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Employee;
use App\Models\ProductVariant;
use App\Models\StockEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'description' => fake()->sentence(),
            'parent_id' => null,
        ];
    }
}
