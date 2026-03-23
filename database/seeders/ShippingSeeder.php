<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShippingSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // --- 1️⃣ Shipping Zones ---
            $zones = [
                ['name' => 'Lagos'],
                ['name' => 'South West'],
                ['name' => 'South East'],
                ['name' => 'South South'],
                ['name' => 'North Central'],
                ['name' => 'North East'],
                ['name' => 'North West'],
                ['name' => 'National'],
            ];
            DB::table('shipping_zones')->insert(array_map(fn($z) => [
                ...$z, 'created_at' => now(), 'updated_at' => now()
            ], $zones));

            $zoneMap = DB::table('shipping_zones')->pluck('id', 'name');

            // --- 2️⃣ Zone → States mapping ---
            $zoneStates = [
                'Lagos' => ['Lagos'],
                'South West' => ['Ogun State', 'Oyo State', 'Osun State', 'Ondo State', 'Ekiti State'],
                'South East' => ['Anambra State', 'Enugu State', 'Abia State', 'Ebonyi State', 'Imo State'],
                'South South' => ['Rivers State', 'Akwa Ibom State', 'Cross River State', 'Delta State', 'Bayelsa State', 'Edo State'],
                'North Central' => ['FCT', 'Niger State', 'Kogi State', 'Kwara State', 'Benue State', 'Nasarawa State', 'Plateau State'],
                'North East' => ['Adamawa State', 'Bauchi State', 'Borno State', 'Gombe State', 'Taraba State', 'Yobe State'],
                'North West' => ['Kano State', 'Katsina State', 'Kaduna State', 'Kebbi State', 'Sokoto State', 'Zamfara State', 'Jigawa State'],
                'National' => [] // covers all
            ];

            // Fetch all states once
            $states = DB::table('states')->pluck('id', 'name')->toArray();

            // Prepare all inserts
            $zoneStateData = [];

            foreach ($zoneStates as $zone => $stateNames) {
                $zoneId = $zoneMap[$zone] ?? null;

                if (!$zoneId || empty($stateNames)) {
                    continue;
                }

                foreach ($stateNames as $stateName) {
                    if (isset($states[$stateName])) {
                        $zoneStateData[] = [
                            'shipping_zone_id' => $zoneId,
                            'state_id' => $states[$stateName],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }

            // Perform bulk insert for efficiency
            if (!empty($zoneStateData)) {
                DB::table('shipping_zone_states')->insert($zoneStateData);
            }


            // --- 3️⃣ Shipping Methods ---
            $methods = [
                ['name' => 'Standard Delivery', 'description' => 'Reliable doorstep delivery.', 'method_type' => 'delivery', 'sort_order' => 1, 'is_active' => true],
                ['name' => 'Express Delivery', 'description' => 'Faster premium delivery.', 'method_type' => 'delivery', 'sort_order' => 2, 'is_active' => true],
                ['name' => 'Pickup', 'description' => 'Collect your order from a pickup point.', 'method_type' => 'pickup', 'sort_order' => 3, 'is_active' => true],
            ];
            DB::table('shipping_methods')->insert(array_map(fn($m) => [
                ...$m, 'created_at' => now(), 'updated_at' => now()
            ], $methods));

            $methodMap = DB::table('shipping_methods')->pluck('id', 'name');

// --- 4️⃣ Shipping Rates ---
            $now = Carbon::now();
            $rates = [
                // Lagos Zone
                [
                    'shipping_method_id' => $methodMap['Standard Delivery'],
                    'shipping_zone_id' => $zoneMap['Lagos'],
                    'min_weight' => 0,
                    'max_weight' => 5,
                    'min_subtotal' => null,
                    'max_subtotal' => null,
                    'rate_type' => 'flat',
                    'base_rate' => 1500,
                    'per_kg' => 0,
                    'surcharge' => 0,
                    'free_shipping_threshold' => 30000,
                    'currency' => 'NGN',
                    'starts_at' => $now,
                    'ends_at' => null,
                    'is_active' => true,
                ],
                [
                    'shipping_method_id' => $methodMap['Express Delivery'],
                    'shipping_zone_id' => $zoneMap['Lagos'],
                    'min_weight' => 0,
                    'max_weight' => 5,
                    'min_subtotal' => null,
                    'max_subtotal' => null,
                    'rate_type' => 'flat',
                    'base_rate' => 2500,
                    'per_kg' => 0,
                    'surcharge' => 0,
                    'free_shipping_threshold' => null,
                    'currency' => 'NGN',
                    'starts_at' => $now,
                    'ends_at' => null,
                    'is_active' => true,
                ],

                // South West
                [
                    'shipping_method_id' => $methodMap['Standard Delivery'],
                    'shipping_zone_id' => $zoneMap['South West'],
                    'min_weight' => 0,
                    'max_weight' => 10,
                    'min_subtotal' => null,
                    'max_subtotal' => null,
                    'rate_type' => 'per_kg',
                    'base_rate' => 1000,
                    'per_kg' => 400,
                    'surcharge' => 0,
                    'free_shipping_threshold' => null,
                    'currency' => 'NGN',
                    'starts_at' => $now,
                    'ends_at' => null,
                    'is_active' => true,
                ],

                // South East
                [
                    'shipping_method_id' => $methodMap['Standard Delivery'],
                    'shipping_zone_id' => $zoneMap['South East'],
                    'min_weight' => 0,
                    'max_weight' => 10,
                    'min_subtotal' => null,
                    'max_subtotal' => null,
                    'rate_type' => 'per_kg',
                    'base_rate' => 1500,
                    'per_kg' => 500,
                    'surcharge' => 500,
                    'free_shipping_threshold' => null,
                    'currency' => 'NGN',
                    'starts_at' => $now,
                    'ends_at' => null,
                    'is_active' => true,
                ],
                [
                    'shipping_method_id' => $methodMap['Express Delivery'],
                    'shipping_zone_id' => $zoneMap['South East'],
                    'min_weight' => 0,
                    'max_weight' => 10,
                    'min_subtotal' => null,
                    'max_subtotal' => null,
                    'rate_type' => 'hybrid',
                    'base_rate' => 2500,
                    'per_kg' => 600,
                    'surcharge' => 500,
                    'free_shipping_threshold' => null,
                    'currency' => 'NGN',
                    'starts_at' => $now,
                    'ends_at' => null,
                    'is_active' => true,
                ],

                // National
                [
                    'shipping_method_id' => $methodMap['Standard Delivery'],
                    'shipping_zone_id' => $zoneMap['National'],
                    'min_weight' => 0,
                    'max_weight' => null,
                    'min_subtotal' => null,
                    'max_subtotal' => null,
                    'rate_type' => 'flat',
                    'base_rate' => 3500,
                    'per_kg' => 0,
                    'surcharge' => 0,
                    'free_shipping_threshold' => null,
                    'currency' => 'NGN',
                    'starts_at' => $now,
                    'ends_at' => null,
                    'is_active' => true,
                ],
            ];

            DB::table('shipping_rates')->insert(array_map(fn($r) => [
                ...$r,
                'created_at' => now(),
                'updated_at' => now(),
            ], $rates));


            // --- 5️⃣ Pickup Locations ---
            $country = DB::table('countries')->where('name', 'Nigeria')->first();
            $lagos = DB::table('states')->where('name', 'Lagos State')->first();
            $oyo = DB::table('states')->where('name', 'Oyo State')->first();

            $pickups = [
                [
                    'shipping_method_id' => $methodMap['Pickup'],
                    'shipping_zone_id' => $zoneMap['Lagos'],
                    'name' => 'Ikeja Main Store',
                    'address_line1' => '12 Allen Avenue, Beside Tantalizers',
                    'state_code' => 'LAG',
                    'postal_code' => '100271',
                    'country_id' => $country?->id,
                    'state_id' => $lagos?->id,
                    'latitude' => 6.6018,
                    'longitude' => 3.3515,
                    'phone' => '+2348123456789',
                    'email' => 'pickup.ikeja@shop.com',
                    'timezone' => 'Africa/Lagos',
                    'opening_hours' => json_encode([
                        'mon-fri' => ['08:00-18:00'],
                        'sat' => ['09:00-16:00'],
                        'sun' => [],
                    ]),
                    'slot_duration_minutes' => 30,
                    'capacity_per_slot' => 10,
                    'lead_time_hours' => 2,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'shipping_method_id' => $methodMap['Pickup'],
                    'shipping_zone_id' => $zoneMap['South West'],
                    'name' => 'Ibadan Pickup Center',
                    'address_line1' => '21 Challenge Road, Opposite Total Filling Station',
                    'state_code' => 'OYO',
                    'postal_code' => '200273',
                    'country_id' => $country?->id,
                    'state_id' => $oyo?->id,
                    'latitude' => 7.3775,
                    'longitude' => 3.9470,
                    'phone' => '+2348098765432',
                    'email' => 'pickup.ibadan@shop.com',
                    'timezone' => 'Africa/Lagos',
                    'opening_hours' => json_encode([
                        'mon-fri' => ['08:00-17:00'],
                        'sat' => ['09:00-15:00'],
                        'sun' => [],
                    ]),
                    'slot_duration_minutes' => 30,
                    'capacity_per_slot' => 8,
                    'lead_time_hours' => 3,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ];

            DB::table('pickup_locations')->insert($pickups);

        });
    }
}
