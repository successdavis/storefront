<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;
use Illuminate\Support\Facades\Hash;

class BusinessSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $businessSettings = [
            ['key' => 'business_name', 'value' => 'S-Techmax Ltd'],
            ['key' => 'business_tagline', 'value' => 'Innovating the Future'],
            ['key' => 'business_email', 'value' => 'support@stechmax.com'],
            ['key' => 'business_phone', 'value' => null],
            ['key' => 'business_address', 'value' => 'No. 12 Example Street, Lagos, Nigeria'],
            ['key' => 'business_website', 'value' => 'https://stechmax.com'],
            ['key' => 'business_logo', 'value' => null],
            ['key' => 'business_currency', 'value' => '₦'],
            ['key' => 'business_receipt_footer', 'value' => 'Thank you for your business!'],
            ['key' => 'business_receipt_footer_refund', 'value' => null],
            ['key' => 'business_tax_id', 'value' => null],
            ['key' => 'barcode_paper_size', 'value' => '50mm'], // Default barcode label setting
            ['key' => 'barcode_label_orientation', 'value' => 'portrait'], // Default barcode orientation
            ['key' => 'barcode_label_height_mm', 'value' => '25'], // Default barcode label height
            ['key' => 'receipt_paper_size', 'value' => '80mm'], // Default print setting
            ['key' => 'about_us_description', 'value' => null], // Default print setting
            ['key' => 'youtube_link', 'value' => null], // Default print setting
            ['key' => 'instagram_link', 'value' => null], // Default print setting
            ['key' => 'facebook_link', 'value' => null], // Default print setting
            ['key' => 'pos_supervisor_password', 'value' => Hash::make('password')], // Default print setting
            ['key' => 'use_pos_terminal_password', 'value' => true], // Default print setting
        ];

        foreach ($businessSettings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}
