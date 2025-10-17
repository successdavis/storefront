<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\Country;
use App\Models\State;
use App\Models\Lga;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        // ===== 1️⃣ Seed Countries =====
        $countriesPath = database_path('seeders/data/countries.json');
        $countries = json_decode(File::get($countriesPath), true);

        foreach ($countries as $countryData) {
            Country::firstOrCreate(
                ['name' => $countryData['name']],
                ['iso2' => $countryData['iso2'] ?? null]
            );
        }

        // ===== 2️⃣ Seed Nigerian States + LGAs =====
        $statesPath = database_path('seeders/data/nigeria-states.json');
        $nigeriaStates = json_decode(File::get($statesPath), true);

        // Find Nigeria
        $nigeria = Country::where('name', 'Nigeria')->first();

        if (!$nigeria) {
            $this->command->warn('Nigeria not found in countries.json, skipping state import.');
            return;
        }


        foreach ($nigeriaStates as $stateData) {
            $state = State::firstOrCreate(
                [
                    'country_id' => $nigeria->id,
                    'name' => $stateData['state']['name']
                ],
                ['code' => $stateData['state']['code'] ?? null]
            );

            if (!empty($stateData['state']['locals'])) {
                foreach ($stateData['state']['locals'] as $lgaData) {
                    Lga::firstOrCreate([
                        'state_id' => $state->id,
                        'name' => $lgaData['name']
                    ]);
                }
            }
        }

        $this->command->info('✅ Countries, states, and LGAs seeded successfully!');
    }
}
