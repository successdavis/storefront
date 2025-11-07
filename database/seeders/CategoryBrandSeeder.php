<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Brand;

class CategoryBrandSeeder extends Seeder
{
    public function run(): void
    {
        /*****************************************
         *            CATEGORIES
         *****************************************/
        $categories = [
            [
                'name' => 'Laptops',
                'description' => 'Brand new and used laptops from top brands including HP, Dell, Lenovo, and Apple MacBooks.',
                'featured' => true,
                'order' => 1,
                'meta_title' => 'Buy Laptops - HP, Dell, Lenovo, Apple',
                'meta_description' => 'Shop brand new and used laptops: HP, Dell, Lenovo, Apple MacBook — best prices guaranteed.',
                'children' => [
                    [
                        'name' => 'Laptop Accessories',
                        'description' => 'Laptop chargers, batteries, bags, SSD, RAM, keyboards, mouse, and docking stations.',
                        'order' => 2,
                        'meta_title' => 'Laptop Accessories - Chargers, SSD, RAM',
                        'meta_description' => 'Buy laptop accessories such as chargers, SSD, RAM, batteries, and docking stations.'
                    ],
                ]
            ],
            [
                'name' => 'Phones',
                'description' => 'Latest mobile phones including Androids, iPhones and UK/US used iPhones.',
                'featured' => true,
                'order' => 3,
                'meta_title' => 'Buy Mobile Phones - Android & iPhones',
                'meta_description' => 'Shop latest Android phones and Apple iPhones — brand new and UK/US used.',
                'children' => [
                    [
                        'name' => 'Android Phones',
                        'description' => 'Samsung, Xiaomi, Tecno, Infinix and more.',
                        'order' => 4,
                        'meta_title' => 'Android Phones - Samsung, Xiaomi, Tecno',
                        'meta_description' => 'Latest Android phones — Samsung, Xiaomi, Tecno, Infinix.',
                    ],
                    [
                        'name' => 'iPhones',
                        'description' => 'Brand new latest Apple iPhones.',
                        'order' => 5,
                        'meta_title' => 'Apple iPhones - Brand New',
                        'meta_description' => 'Latest brand new Apple iPhones available for immediate pickup or delivery.',
                    ],
                    [
                        'name' => 'Used iPhones',
                        'description' => 'Clean UK/US used Apple iPhones.',
                        'order' => 6,
                        'meta_title' => 'Used iPhones - UK / US Used',
                        'meta_description' => 'Buy clean UK/US used Apple iPhones tested and certified.',
                    ]
                ]
            ],
            [
                'name' => 'Solar & Inverters',
                'description' => 'Solar panels, inverters, batteries, charge controllers and installation supplies.',
                'featured' => false,
                'order' => 7,
                'meta_title' => 'Solar Panels, Inverters & Batteries',
                'meta_description' => 'Buy inverter systems, solar panels, batteries, and solar installation kits.',
                'children' => [
                    [
                        'name' => 'Solar Accessories',
                        'description' => 'MC4 connectors, solar cables, breakers and other installation materials.',
                        'order' => 8,
                        'meta_title' => 'Solar Accessories - MC4, Cables, Breakers',
                        'meta_description' => 'Buy solar accessories including connectors, cables, breakers and installation materials.',
                    ]
                ]
            ],
            [
                'name' => 'Printers',
                'description' => 'Inkjet & LaserJet printers, scanners, photocopiers and printer consumables.',
                'featured' => false,
                'order' => 9,
                'meta_title' => 'Printers - HP, Canon, Epson',
                'meta_description' => 'Buy printers and printer consumables — HP, Canon, Epson.',
            ]
        ];

        foreach ($categories as $cat) {

            $children = $cat['children'] ?? null;
            unset($cat['children']);

            $parent = Category::create([
                'name' => $cat['name'],
                'slug' => Str::slug($cat['name']),
                'description' => $cat['description'] ?? null,
                'featured' => $cat['featured'] ?? false,
                'order' => $cat['order'] ?? 0,
                'meta_title' => $cat['meta_title'] ?? null,
                'meta_description' => $cat['meta_description'] ?? null,
                'banner' => null,
                'icon' => null,
                'cover_image' => null,
                'parent_id' => null,
            ]);

            if ($children) {
                foreach ($children as $child) {
                    Category::create([
                        'name' => $child['name'],
                        'slug' => Str::slug($child['name']),
                        'description' => $child['description'] ?? null,
                        'featured' => false,
                        'order' => $child['order'] ?? 0,
                        'meta_title' => $child['meta_title'] ?? null,
                        'meta_description' => $child['meta_description'] ?? null,
                        'parent_id' => $parent->id,
                        'banner' => null,
                        'icon' => null,
                        'cover_image' => null,
                    ]);
                }
            }
        }

        /*****************************************
         *               BRANDS
         *****************************************/
        $brands = [
            ['name' => 'Apple', 'top_brand' => true],
            ['name' => 'HP', 'top_brand' => true],
            ['name' => 'Dell', 'top_brand' => true],
            ['name' => 'Lenovo', 'top_brand' => true],
            ['name' => 'Asus', 'top_brand' => false],
            ['name' => 'Acer', 'top_brand' => false],
            ['name' => 'Samsung', 'top_brand' => true],
            ['name' => 'Tecno', 'top_brand' => false],
            ['name' => 'Infinix', 'top_brand' => false],
            ['name' => 'Xiaomi', 'top_brand' => false],
            ['name' => 'Canon', 'top_brand' => false],
            ['name' => 'Epson', 'top_brand' => false],
            ['name' => 'Brother', 'top_brand' => false],
        ];

        foreach ($brands as $brand) {
            Brand::create([
                'name' => $brand['name'],
                'slug' => Str::slug($brand['name']),
                'top_brand' => $brand['top_brand'],
                'meta_title' => "{$brand['name']} Official Products",
                'meta_description' => "Shop genuine {$brand['name']} products and accessories.",
                'description' => "Official {$brand['name']} devices and accessories.",
                'logo' => null,
            ]);
        }
    }
}
