<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use App\Models\User;
use App\Models\Employee;
use App\Models\PosTerminal;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\StockEntry;
use App\Models\ProductVariant;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\VariantType;
use App\Models\VariantValue;
use App\Models\ProductVariantValue;

use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- Helpers as closures (no global function redeclare) ----------
        $seedVariantCatalog = function (): void {
            $color = VariantType::firstOrCreate(['name' => 'Color']);
            foreach (['Black','Silver','Blue','Red','White'] as $v) {
                VariantValue::firstOrCreate([
                    'variant_type_id' => $color->id,
                    'value'           => $v,
                ]);
            }

            $storage = VariantType::firstOrCreate(['name' => 'Storage']);
            foreach (['256GB','512GB','1TB'] as $v) {
                VariantValue::firstOrCreate([
                    'variant_type_id' => $storage->id,
                    'value'           => $v,
                ]);
            }
        };

        $cartesian = function (array $lists): array {
            // $lists: [type_id => [VariantValue, ...]]
            if (empty($lists)) return [];
            $result = [[]];
            foreach ($lists as $typeId => $values) {
                $next = [];
                foreach ($result as $partial) {
                    foreach ($values as $val) {
                        $next[] = $partial + [$typeId => $val];
                    }
                }
                $result = $next;
            }
            return $result;
        };

        // ---------- Base data ----------
        $categories = Category::factory(5)->create();
        $brands     = Brand::factory(3)->create();

        // Reusable variant catalog
        $seedVariantCatalog();

        // ---------- Products + Variants ----------
        Product::factory(100)
            ->make()
            ->each(function (Product $product) use ($categories, $brands, $cartesian) {
                DB::transaction(function () use ($product, $categories, $brands, $cartesian) {
                    // Attach existing IDs (fixes the "Incorrect integer value" error)
                    $product->brand_id    = $brands->random()->id;

                    // Ensure slug is set by factory or an observer before insert
                    if (empty($product->slug)) {
                        $base = Str::slug($product->name);
                        $product->slug = Str::limit($base, 150, '') . '-' . Str::lower(Str::random(6));
                    }

                    $product->save();

                    $product->categories()->attach(
                        $categories->random(rand(1, 3))->pluck('id')->toArray()
                    );


                    // Choose 1–2 variant types for this product
                    $types = VariantType::inRandomOrder()->take(2)->get();
                    if ($types->isEmpty()) {
                        // Safety guard in case catalog is empty
                        $types = VariantType::all();
                    }

                    // Pick up to 3 values per chosen type
                    $valuesByType = [];
                    foreach ($types as $type) {
                        $valuesByType[$type->id] = VariantValue::where('variant_type_id', $type->id)
                            ->inRandomOrder()
                            ->take(3)
                            ->get()
                            ->all();
                    }

                    $combos = $cartesian($valuesByType);
                    if (empty($combos)) {
                        // If no combos (e.g., only one value total), make a single variant without values
                        $variant = ProductVariant::factory()->create([
                            'product_id' => $product->id,
                            'sku'        => Str::upper(Str::slug($product->name)) . '-' . Str::random(6),
                        ]);
                        return;
                    }

                    // Create 2 variants per product (or fewer if less combos)
                    $selected = Arr::wrap(Arr::random($combos, min(2, count($combos))));

                    foreach ($selected as $combo) {
                        $suffix = collect($combo)
                            ->map(fn(VariantValue $v) => Str::upper(Str::slug($v->value)))
                            ->implode('-');

                        $variant = ProductVariant::factory()->create([
                            'product_id' => $product->id,
                            'sku'        => Str::upper(Str::slug($product->name)) . '-' . $suffix . '-' . Str::random(4),
                        ]);

                        foreach ($combo as $value) {
                            ProductVariantValue::create([
                                'product_variant_id' => $variant->id,
                                'variant_value_id'   => $value->id,
                            ]);
                        }
                    }
                });
            });

        // ---------- Users ----------
        User::factory(20)->create();

        // ---------- Employees & POS ----------
        $employees    = User::factory(5)->create();
        $posTerminals = PosTerminal::factory(3)->create();

        // ---------- Orders ----------
        Order::factory(10)
            ->create()
            ->each(function (Order $order) {
                OrderItem::factory(3)->create([
                    'order_id' => $order->id,
                ]);
            });

        // ---------- Sales ----------
        Sale::factory(10)
            ->create([
                'employee_id'    => $employees->random()->id,
                'pos_terminal_id'=> $posTerminals->random()->id,
                'customer_id'        => User::inRandomOrder()->value('id'),
            ])
            ->each(function (Sale $sale) {
                SaleItem::factory(3)->create(['sale_id' => $sale->id]);
//                SalePayment::factory()->create(['sale_id' => $sale->id]);
            });

        // ---------- Stock ----------
        StockEntry::factory(20)->create();

        // ---------- Admin + Role ----------
        $directorRole = Role::firstOrCreate(['name' => 'director']);

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin User', 'password' => Hash::make('password')]
        );

        if (! $admin->hasRole('director')) {
            $admin->assignRole($directorRole);
        }
    }
}
