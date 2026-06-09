<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Support\RoleNames;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class AdminBusinessSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_view_business_settings_page(): void
    {
        $this->withoutVite();

        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        Setting::set('business_name', 'S-Techmax Ltd');
        Setting::set('barcode_paper_size', '58mm');
        Setting::set('barcode_label_orientation', 'portrait');
        Setting::set('barcode_label_height_mm', '25');
        Setting::set('receipt_paper_size', 'A4');

        $this->actingAs($director)
            ->get(route('admin.business-settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/BusinessSettings')
                ->where('settings.business_name', 'S-Techmax Ltd')
                ->where('settings.barcode_paper_size', '58mm')
                ->where('settings.barcode_label_orientation', 'portrait')
                ->where('settings.barcode_label_height_mm', '25')
                ->where('settings.receipt_paper_size', 'A4')
                ->where('paper_options.barcode.0.value', '50mm')
                ->where('paper_options.receipt.0.value', '58mm')
                ->where('orientation_options.0.value', 'portrait')
            );
    }

    public function test_director_can_update_business_settings(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $payload = [
            'business_name' => 'Demo Retail Ltd',
            'business_tagline' => 'Everyday inventory',
            'business_email' => 'ops@example.com',
            'business_phone' => '+2348000000000',
            'business_address' => '12 Demo Street, Lagos',
            'business_website' => 'https://example.com',
            'business_currency' => 'NGN',
            'business_tax_id' => 'TIN-123',
            'business_receipt_footer' => 'Thanks for shopping with us.',
            'business_receipt_footer_refund' => 'Returns require a receipt.',
            'barcode_paper_size' => '50mm',
            'barcode_label_orientation' => 'portrait',
            'barcode_label_height_mm' => '25',
            'receipt_paper_size' => '80mm',
        ];

        $this->actingAs($director)
            ->patch(route('admin.business-settings.update'), $payload)
            ->assertRedirect();

        foreach ($payload as $key => $value) {
            $this->assertDatabaseHas('settings', [
                'key' => $key,
                'value' => $value,
            ]);
        }
    }

    public function test_customer_cannot_access_business_settings(): void
    {
        $customer = User::factory()->create();
        $customer->syncRoles([RoleNames::CUSTOMER]);

        $this->actingAs($customer)
            ->get(route('admin.business-settings.edit'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->patch(route('admin.business-settings.update'), [
                'business_name' => 'Blocked',
                'barcode_paper_size' => '50mm',
                'barcode_label_orientation' => 'portrait',
                'barcode_label_height_mm' => '25',
                'receipt_paper_size' => '80mm',
            ])
            ->assertForbidden();
    }

    public function test_barcode_print_uses_barcode_paper_setting_independently_from_receipts(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $product = Product::factory()->create(['name' => 'Demo Product']);
        $variant = ProductVariant::factory()
            ->for($product)
            ->create([
                'barcode' => '123456789012',
                'sku' => 'DEMO-SKU',
                'regular_price' => 25000,
            ]);

        Setting::set('business_currency', 'NGN');
        Setting::set('receipt_paper_size', 'A4');
        Setting::set('barcode_paper_size', '50mm');
        Setting::set('barcode_label_orientation', 'portrait');
        Setting::set('barcode_label_height_mm', '25');

        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')
            ->once()
            ->with(
                Mockery::on(fn ($paper): bool => is_array($paper)
                    && round((float) $paper[2], 2) === 141.73
                    && round((float) $paper[3], 2) === 70.87),
                'portrait',
            )
            ->andReturnSelf();
        $pdf->shouldReceive('stream')
            ->once()
            ->andReturn(response('PDF', 200, ['Content-Type' => 'application/pdf']));

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('barcode-labels', Mockery::on(function (array $data): bool {
                return $data['paper_size'] === '50mm'
                    && $data['orientation'] === 'portrait'
                    && (float) $data['label_height_mm'] === 25.0
                    && $data['columns'] === 1
                    && $data['labels'][0]['barcode'] === '123456789012'
                    && $data['labels'][0]['price'] === '₦25,000.00';
            }))
            ->andReturn($pdf);

        $this->actingAs($director)
            ->post(route('barcodes.print'), ['variant_ids' => [$variant->id]])
            ->assertOk()
            ->assertSee('PDF');
    }

    public function test_barcode_landscape_layout_keeps_thermal_paper_width_at_50mm(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $product = Product::factory()->create(['name' => 'Landscape Product']);
        $variant = ProductVariant::factory()
            ->for($product)
            ->create([
                'barcode' => '987654321098',
                'regular_price' => 18000,
            ]);

        Setting::set('business_currency', 'NGN');
        Setting::set('barcode_paper_size', '50mm');
        Setting::set('barcode_label_orientation', 'landscape');
        Setting::set('barcode_label_height_mm', '25');

        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')
            ->once()
            ->with(
                Mockery::on(fn ($paper): bool => is_array($paper)
                    && round((float) $paper[2], 2) === 70.87
                    && round((float) $paper[3], 2) === 141.73),
                'landscape',
            )
            ->andReturnSelf();
        $pdf->shouldReceive('stream')
            ->once()
            ->andReturn(response('PDF', 200, ['Content-Type' => 'application/pdf']));

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('barcode-labels', Mockery::on(fn (array $data): bool => $data['paper_size'] === '50mm'
                && $data['orientation'] === 'landscape'
                && (float) $data['label_height_mm'] === 25.0
                && $data['labels'][0]['barcode'] === '987654321098'))
            ->andReturn($pdf);

        $this->actingAs($director)
            ->post(route('barcodes.print'), ['variant_ids' => [$variant->id]])
            ->assertOk()
            ->assertSee('PDF');
    }

    public function test_batch_thermal_barcode_print_keeps_one_label_sized_pages(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $product = Product::factory()->create(['name' => 'Batch Product']);
        $firstVariant = ProductVariant::factory()
            ->for($product)
            ->create([
                'barcode' => '111111111111',
                'regular_price' => 12000,
            ]);
        $secondVariant = ProductVariant::factory()
            ->for($product)
            ->create([
                'barcode' => '222222222222',
                'regular_price' => 15000,
            ]);

        Setting::set('business_currency', 'NGN');
        Setting::set('barcode_paper_size', '50mm');
        Setting::set('barcode_label_orientation', 'landscape');
        Setting::set('barcode_label_height_mm', '25');

        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')
            ->once()
            ->with(
                Mockery::on(fn ($paper): bool => is_array($paper)
                    && round((float) $paper[2], 2) === 70.87
                    && round((float) $paper[3], 2) === 141.73),
                'landscape',
            )
            ->andReturnSelf();
        $pdf->shouldReceive('stream')
            ->once()
            ->andReturn(response('PDF', 200, ['Content-Type' => 'application/pdf']));

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('barcode-labels', Mockery::on(fn (array $data): bool => $data['paper_size'] === '50mm'
                && $data['orientation'] === 'landscape'
                && (float) $data['label_height_mm'] === 25.0
                && count($data['labels']) === 2
                && $data['labels'][0]['barcode'] === '111111111111'
                && $data['labels'][1]['barcode'] === '222222222222'))
            ->andReturn($pdf);

        $this->actingAs($director)
            ->post(route('barcodes.print'), ['variant_ids' => [$firstVariant->id, $secondVariant->id]])
            ->assertOk()
            ->assertSee('PDF');
    }

    public function test_single_landscape_thermal_barcode_renders_as_one_pdf_page(): void
    {
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app('dompdf.wrapper');

        $pdf->loadView('barcode-labels', [
            'labels' => [[
                'name' => '20000mah itel powerbank + 20000',
                'barcode' => '2603200066067',
                'price' => 'NGN 18,000.00',
            ]],
            'columns' => 1,
            'paper_size' => '50mm',
            'orientation' => 'landscape',
            'label_height_mm' => 25,
        ])->setPaper([0, 0, 70.87, 141.73], 'landscape');

        $pdf->render();

        $paperSize = $pdf->getDomPDF()->getPaperSize();

        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertEqualsWithDelta(141.73, $paperSize[2], 0.01);
        $this->assertEqualsWithDelta(70.87, $paperSize[3], 0.01);
    }

    public function test_multiple_thermal_barcodes_render_each_label_on_its_own_pdf_page(): void
    {
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app('dompdf.wrapper');

        $pdf->loadView('barcode-labels', [
            'labels' => [
                [
                    'name' => 'First batch product',
                    'barcode' => '111111111111',
                    'price' => 'NGN 12,000.00',
                ],
                [
                    'name' => 'Second batch product',
                    'barcode' => '222222222222',
                    'price' => 'NGN 15,000.00',
                ],
                [
                    'name' => 'Third batch product',
                    'barcode' => '333333333333',
                    'price' => 'NGN 18,000.00',
                ],
            ],
            'columns' => 1,
            'paper_size' => '50mm',
            'orientation' => 'landscape',
            'label_height_mm' => 25,
        ])->setPaper([0, 0, 70.87, 141.73], 'landscape');

        $pdf->render();

        $paperSize = $pdf->getDomPDF()->getPaperSize();

        $this->assertSame(3, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertEqualsWithDelta(141.73, $paperSize[2], 0.01);
        $this->assertEqualsWithDelta(70.87, $paperSize[3], 0.01);
    }
}
