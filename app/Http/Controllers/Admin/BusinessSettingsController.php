<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessSettingsController extends Controller
{
    private const SECTION_FIELDS = [
        'profile' => [
            'business_name',
            'business_tagline',
            'business_currency',
            'business_tax_id',
        ],
        'contact_receipt' => [
            'business_email',
            'business_phone',
            'business_website',
            'business_address',
            'business_receipt_footer',
            'business_receipt_footer_refund',
        ],
        'print' => [
            'barcode_paper_size',
            'barcode_label_orientation',
            'barcode_label_height_mm',
            'receipt_paper_size',
        ],
    ];

    private const SETTING_DEFAULTS = [
        'business_name' => '',
        'business_tagline' => '',
        'business_email' => '',
        'business_phone' => '',
        'business_address' => '',
        'business_website' => '',
        'business_currency' => 'NGN',
        'business_tax_id' => '',
        'business_receipt_footer' => '',
        'business_receipt_footer_refund' => '',
        'barcode_paper_size' => '50mm',
        'barcode_label_orientation' => 'portrait',
        'barcode_label_height_mm' => '25',
        'receipt_paper_size' => '80mm',
    ];

    public function edit(): Response
    {
        return Inertia::render('Admin/BusinessSettings', [
            'settings' => collect(self::SETTING_DEFAULTS)
                ->mapWithKeys(fn (string $default, string $key) => [$key => Setting::get($key, $default)])
                ->all(),
            'paper_options' => [
                'barcode' => $this->paperOptions(['50mm', '58mm', '80mm', 'A4']),
                'receipt' => $this->paperOptions(['58mm', '80mm', 'A4']),
            ],
            'orientation_options' => $this->paperOptions(['portrait', 'landscape']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $section = $request->string('section')->toString();
        $rules = $this->validationRules();

        if ($section !== '') {
            $request->validate([
                'section' => ['required', 'string', 'in:' . implode(',', array_keys(self::SECTION_FIELDS))],
            ]);

            $rules = collect(self::SECTION_FIELDS[$section])
                ->mapWithKeys(fn (string $key): array => [$key => $rules[$key]])
                ->all();
        }

        $validated = $request->validate($rules);
        $keys = $section !== ''
            ? self::SECTION_FIELDS[$section]
            : array_keys(self::SETTING_DEFAULTS);

        foreach ($keys as $key) {
            Setting::set($key, $validated[$key] ?? null);
        }

        return back()->with('success', 'Business settings updated.');
    }

    /**
     * @return array<string, list<string>>
     */
    private function validationRules(): array
    {
        return [
            'business_name' => ['nullable', 'string', 'max:255'],
            'business_tagline' => ['nullable', 'string', 'max:255'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:50'],
            'business_address' => ['nullable', 'string', 'max:1000'],
            'business_website' => ['nullable', 'url', 'max:255'],
            'business_currency' => ['nullable', 'string', 'max:10'],
            'business_tax_id' => ['nullable', 'string', 'max:100'],
            'business_receipt_footer' => ['nullable', 'string', 'max:1000'],
            'business_receipt_footer_refund' => ['nullable', 'string', 'max:1000'],
            'barcode_paper_size' => ['required', 'string', 'in:50mm,58mm,80mm,A4'],
            'barcode_label_orientation' => ['required', 'string', 'in:portrait,landscape'],
            'barcode_label_height_mm' => ['required', 'numeric', 'min:10', 'max:500'],
            'receipt_paper_size' => ['required', 'string', 'in:58mm,80mm,A4'],
        ];
    }

    /**
     * @param  list<string>  $values
     * @return list<array{value: string, label: string}>
     */
    private function paperOptions(array $values): array
    {
        return array_map(
            fn (string $value): array => ['value' => $value, 'label' => $value],
            $values,
        );
    }
}
