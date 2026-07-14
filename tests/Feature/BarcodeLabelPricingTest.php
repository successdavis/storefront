<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\RoleNames;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BarcodeLabelPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_barcode_labels_use_discounted_price_when_automatic_discount_is_active(): void
    {
        $variant = $this->makeVariant(100);

        Discount::query()->create([
            'name' => 'Global 20',
            'description' => 'Global automatic discount',
            'code' => null,
            'type' => Discount::TYPE_PERCENTAGE,
            'value' => 20,
            'application_method' => Discount::APPLICATION_LINE_ITEM,
            'min_order_amount' => null,
            'usage_limit' => null,
            'usage_limit_per_user' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'customer_scope' => Discount::CUSTOMER_SCOPE_ALL,
            'priority' => 0,
            'is_active' => true,
        ]);

        $labels = $this->printAndCaptureLabels($variant);

        $this->assertCount(1, $labels);
        $this->assertStringContainsString('80.00', $labels[0]['price']);
        $this->assertStringNotContainsString('100.00', $labels[0]['price']);
    }

    public function test_barcode_labels_keep_regular_price_without_active_discounts(): void
    {
        $variant = $this->makeVariant(100);

        $labels = $this->printAndCaptureLabels($variant);

        $this->assertCount(1, $labels);
        $this->assertStringContainsString('100.00', $labels[0]['price']);
    }

    public function test_sales_representative_can_print_barcode_labels(): void
    {
        $variant = $this->makeVariant(100);

        $rep = User::factory()->create();
        $rep->syncRoles([RoleNames::SALES_REPRESENTATIVE]);

        $labels = $this->printAndCaptureLabels($variant, $rep);

        $this->assertCount(1, $labels);
    }

    public function test_customer_cannot_print_barcode_labels(): void
    {
        $variant = $this->makeVariant(100);

        $customer = User::factory()->create();
        $customer->syncRoles([RoleNames::CUSTOMER]);

        $this->actingAs($customer)
            ->post(route('barcodes.print'), ['variant_ids' => [$variant->id]])
            ->assertForbidden();
    }

    protected function makeVariant(float $regularPrice): ProductVariant
    {
        $product = Product::factory()->create(['name' => 'Labelled Product']);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'regular_price' => $regularPrice,
            'sale_starts_at' => null,
            'sale_ends_at' => null,
            'is_active' => true,
        ]);
    }

    protected function printAndCaptureLabels(ProductVariant $variant, ?User $actor = null): array
    {
        if (!$actor) {
            $actor = User::factory()->create();
            $actor->syncRoles([RoleNames::DIRECTOR]);
        }

        $captured = null;

        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->andReturnSelf();
        $pdf->shouldReceive('stream')->andReturn(response('%PDF'));

        Pdf::shouldReceive('loadView')
            ->once()
            ->withArgs(function (string $view, array $data) use (&$captured) {
                $captured = $data;

                return $view === 'barcode-labels';
            })
            ->andReturn($pdf);

        $this->actingAs($actor)
            ->post(route('barcodes.print'), ['variant_ids' => [$variant->id]])
            ->assertOk();

        $this->assertIsArray($captured, 'barcode-labels view data was not captured');

        return $captured['labels'];
    }
}
