<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WarehouseFactory extends Factory
{
    /** @var string */
    protected $model = Warehouse::class;

    public function definition(): array
    {
        // Generate a short code like "WH-ABC123"
        $code = 'WH-' . strtoupper(Str::random(6));

        return [
            'name'            => $this->faker->company . ' Warehouse',
            'code'            => $code,
            'address'         => $this->faker->streetAddress,
            'city'            => $this->faker->city,
            'state'           => $this->faker->state,
            'country'         => $this->faker->country,
            'contact_person'  => $this->faker->name,
            'phone'           => $this->faker->phoneNumber,
            'email'           => $this->faker->unique()->safeEmail,
            'active'          => $this->faker->boolean(90),  // 90% chance of being active
            'created_at'      => now(),
            'updated_at'      => now(),
        ];
    }

    /**
     * Indicate that the warehouse is inactive.
     */
    public function inactive(): self
    {
        return $this->state(fn () => ['active' => false]);
    }
}
