<?php

namespace Database\Factories;

use App\Models\Warehouse;
use App\Models\Lga;
use App\Models\State;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'name'            => $this->faker->company . ' Warehouse',
            'code'            => 'WH-' . strtoupper(Str::random(6)),
            'address'         => $this->faker->optional()->streetAddress,

            // ✅ Select from already existing records — DO NOT create new
            'lga_id'          => Lga::inRandomOrder()->value('id'),
            'state_id'        => State::inRandomOrder()->value('id'),
            'country_id'      => Country::inRandomOrder()->value('id'),

            'contact_person'  => $this->faker->optional()->name,
            'phone'           => $this->faker->optional()->phoneNumber,
            'email'           => $this->faker->optional()->safeEmail,

            'active'          => $this->faker->boolean(90),
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['active' => false]);
    }
}
