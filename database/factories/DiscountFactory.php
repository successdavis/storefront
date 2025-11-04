<?php

namespace Database\Factories;

use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DiscountFactory extends Factory
{
    protected $model = Discount::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['percentage', 'fixed_amount', 'free_shipping']);
        $value = match ($type) {
            'percentage'    => $this->faker->numberBetween(5, 50), // 5% - 50%
            'fixed_amount'  => $this->faker->randomFloat(2, 500, 5000), // ₦500 - ₦5000
            'free_shipping' => null,
        };

        return [
            'name'                  => ucfirst($this->faker->words(3, true)) . " Discount",
            'code'                  => $this->faker->boolean(70) ? strtoupper(Str::random(8)) : null, // 70% have a code
            'type'                  => $type,
            'value'                 => $value,
            'min_order_amount'      => $this->faker->optional()->randomFloat(2, 1000, 10000),
            'usage_limit'           => $this->faker->optional()->numberBetween(50, 500),
            'usage_limit_per_user'  => $this->faker->optional()->numberBetween(1, 5),
            'starts_at'             => now()->subDays($this->faker->numberBetween(0, 10)),
            'ends_at'               => now()->addDays($this->faker->numberBetween(5, 30)),
            'customer_scope'        => $this->faker->randomElement(['all', 'new_customers', 'selected_customers']),
            'is_active'             => true,
        ];
    }

    /**
     * Indicate the discount is expired.
     */
    public function expired(): self
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(30),
            'ends_at'   => now()->subDays(1),
            'is_active' => false,
        ]);
    }

    /**
     * Attach relations after creation (products, categories, users, variants).
     */
    public function configure(): self
    {
        return $this->afterCreating(function (Discount $discount) {
            // Attach some products
            $products = Product::inRandomOrder()->take(rand(1, 3))->pluck('id');
            if ($products->isNotEmpty()) {
                $discount->products()->attach($products);
            }

            // Attach some variants
            $variants = ProductVariant::inRandomOrder()->take(rand(1, 3))->pluck('id');
            if ($variants->isNotEmpty()) {
                $discount->variants()->attach($variants);
            }

            // Attach some categories
            $categories = Category::inRandomOrder()->take(rand(1, 2))->pluck('id');
            if ($categories->isNotEmpty()) {
                $discount->categories()->attach($categories);
            }

            // Attach selected users if scope = selected_customers
            if ($discount->customer_scope === 'selected_customers') {
                $users = User::inRandomOrder()->take(rand(1, 5))->pluck('id');
                if ($users->isNotEmpty()) {
                    $discount->users()->attach($users->mapWithKeys(fn ($id) => [$id => ['times_used' => 0]]));
                }
            }
        });
    }
}
