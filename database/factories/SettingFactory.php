<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Setting;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->word(),
            'value' => $this->faker->sentence(),
        ];
    }

    /**
     * Custom state for business settings
     */
    public function businessDefaults(): static
    {
        return $this->state(fn () => [
            // we won't return here; we'll use seeder for static inserts
        ]);
    }
}
