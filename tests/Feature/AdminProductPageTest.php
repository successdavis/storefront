<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminProductPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $director;

    protected function setUp(): void
    {
        parent::setUp();

        $this->director = User::factory()->create();
        $this->director->syncRoles([RoleNames::DIRECTOR]);
    }

    public function test_product_pages_are_addressed_by_slug(): void
    {
        $product = Product::factory()->create(['name' => 'MTN Broadband K10', 'slug' => 'mtn-broadband-k10']);

        $this->assertSame(url('/admin/products/mtn-broadband-k10'), route('admin.products.show', $product));
        $this->assertSame(url('/admin/products/mtn-broadband-k10/edit'), route('admin.products.edit', $product));

        $this->actingAs($this->director)
            ->get('/admin/products/mtn-broadband-k10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Products/Show')
                ->where('product.id', $product->id)
            );

        $this->actingAs($this->director)
            ->get('/admin/products/mtn-broadband-k10/edit')
            ->assertOk();
    }

    public function test_old_id_links_and_renamed_slugs_redirect_to_the_current_slug_url(): void
    {
        $product = Product::factory()->create(['name' => 'MTN Broadband K10', 'slug' => 'mtn-broadband-k10']);

        $this->actingAs($this->director)
            ->get("/admin/products/{$product->id}")
            ->assertRedirect('/admin/products/mtn-broadband-k10');

        $this->actingAs($this->director)
            ->get("/admin/products/{$product->id}/edit?step=2")
            ->assertRedirect('/admin/products/mtn-broadband-k10/edit?step=2');

        $product->update(['slug' => 'mtn-broadband-k10-router']);

        $this->actingAs($this->director)
            ->get('/admin/products/mtn-broadband-k10')
            ->assertRedirect('/admin/products/mtn-broadband-k10-router');

        $this->actingAs($this->director)
            ->get('/admin/products/no-such-product')
            ->assertNotFound();
    }

    public function test_product_page_reports_lifetime_units_sold_across_every_variant(): void
    {
        $product = Product::factory()->create();
        $current = ProductVariant::factory()->create(['product_id' => $product->id]);
        $retired = ProductVariant::factory()->create(['product_id' => $product->id]);
        $otherProduct = ProductVariant::factory()->create(['product_id' => Product::factory()->create()->id]);

        $this->sale($current, 2, 'completed', '2026-09-01 10:00:00', pos: true);
        $this->sale($current, 3, 'paid', '2026-09-20 09:30:00');
        $this->sale($retired, 1, 'shipped', '2026-08-15 12:00:00');
        $this->sale($current, 7, 'cancelled', '2026-09-25 08:00:00');
        $this->sale($current, 4, 'pending', '2026-09-26 08:00:00');
        $this->sale($otherProduct, 9, 'completed', '2026-09-27 08:00:00');

        // Deleted variants' past sales still count toward the product.
        $retired->delete();

        $this->actingAs($this->director)
            ->get(route('admin.products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.sales_summary.units_sold', 6)
                ->where('product.sales_summary.orders_count', 3)
                ->where('product.sales_summary.last_sold_at', Carbon::parse('2026-09-20 09:30:00')->toIso8601String())
            );
    }

    public function test_variant_details_include_barcode_image_label_link_and_their_own_sales(): void
    {
        $product = Product::factory()->create();
        $black = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'CORD-BLK']);
        $white = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'CORD-WHT']);

        $this->sale($black, 2, 'completed', '2026-09-01 10:00:00', pos: true);
        $this->sale($black, 1, 'paid', '2026-09-03 10:00:00');
        $this->sale($white, 5, 'cancelled', '2026-09-04 10:00:00');

        $this->actingAs($this->director)
            ->get(route('admin.products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.variants.0.sku', 'CORD-BLK')
                ->where('product.variants.0.sales.units_sold', 3)
                ->where('product.variants.0.sales.orders_count', 2)
                ->where('product.variants.0.sales.last_sold_at', Carbon::parse('2026-09-03 10:00:00')->toIso8601String())
                ->where('product.variants.0.labels_url', route('admin.barcodes.index', ['search' => 'CORD-BLK']))
                ->where('product.variants.0.barcode_image', fn (?string $image) => str_starts_with((string) $image, 'data:image/svg+xml;base64,'))
                ->where('product.variants.1.sku', 'CORD-WHT')
                ->where('product.variants.1.sales.units_sold', 0)
                ->where('product.sales_summary.units_sold', 3)
            );
    }

    public function test_product_without_sales_reports_zero_sold(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->actingAs($this->director)
            ->get(route('admin.products.show', $product))
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.sales_summary.units_sold', 0)
                ->where('product.sales_summary.orders_count', 0)
                ->where('product.sales_summary.last_sold_at', null)
            );
    }

    protected function sale(ProductVariant $variant, int $quantity, string $status, string $at, bool $pos = false): void
    {
        $order = ($pos ? Order::factory()->pos() : Order::factory()->online())
            ->create(['status' => $status, 'created_at' => $at]);

        OrderItem::factory()->forOrder($order)->forVariant($variant)->quantity($quantity)->create();
    }
}
