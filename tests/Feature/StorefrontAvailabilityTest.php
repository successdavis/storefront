<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Category;
use App\Models\Vendor;
use App\Services\CartService;
use App\Services\DropshippingService;
use App\Services\ProductService;
use App\Models\DropshipFulfillment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_products_excludes_products_without_active_variants(): void
    {
        $archivedOnlyProduct = Product::factory()->create([
            'name' => 'Archived Variant Product',
            'created_at' => now(),
        ]);
        ProductVariant::factory()->for($archivedOnlyProduct)->create([
            'quantity' => 10,
            'regular_price' => 120000,
            'is_active' => false,
        ]);

        $sellableProduct = Product::factory()->create([
            'name' => 'Sellable Product',
            'created_at' => now()->subMinute(),
        ]);
        $sellableVariant = ProductVariant::factory()->for($sellableProduct)->create([
            'quantity' => 7,
            'reserved' => 1,
            'regular_price' => 150000,
            'is_active' => true,
        ]);

        $latestProducts = app(ProductService::class)->getLatestProducts(8);

        $this->assertSame([$sellableProduct->id], array_column($latestProducts, 'id'));
        $this->assertSame($sellableVariant->id, $latestProducts[0]['default_variant_id']);
        $this->assertTrue($latestProducts[0]['stock']['is_in_stock']);
    }

    public function test_cart_service_add_item_allows_active_variants_for_active_products(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        $variant = ProductVariant::factory()->for($product)->create([
            'quantity' => 5,
            'reserved' => 0,
            'regular_price' => 95000,
            'is_active' => true,
        ]);

        Redis::shouldReceive('watch')
            ->once()
            ->with("cart:user:{$user->id}");
        Redis::shouldReceive('get')
            ->once()
            ->with("cart:user:{$user->id}")
            ->andReturn(null);
        Redis::shouldReceive('multi')->once();
        Redis::shouldReceive('set')
            ->once()
            ->withArgs(function (string $key, string $payload) use ($user, $variant) {
                if ($key !== "cart:user:{$user->id}") {
                    return false;
                }

                $decoded = json_decode($payload, true);

                return is_array($decoded)
                    && (int) ($decoded[$variant->id]['variant_id'] ?? 0) === $variant->id
                    && (int) ($decoded[$variant->id]['quantity'] ?? 0) === 1;
            });
        Redis::shouldReceive('expire')
            ->once()
            ->with("cart:user:{$user->id}", 60 * 60 * 24 * 30);
        Redis::shouldReceive('exec')
            ->once()
            ->andReturn([true, true]);

        $result = app(CartService::class)->addItem([
            'variant_id' => $variant->id,
            'quantity' => 1,
        ], $user->id);

        $this->assertSame($variant->id, $result['variant_id']);
        $this->assertSame(1, $result['quantity']);
    }

    public function test_dropshipping_variant_is_sellable_without_local_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        $variant = ProductVariant::factory()->for($product)->create([
            'quantity' => 0,
            'reserved' => 0,
            'regular_price' => 95000,
            'is_active' => true,
            'fulfillment_type' => ProductVariant::FULFILLMENT_DROPSHIPPING,
            'is_dropshippable' => true,
            'show_as_available_when_dropshipping' => true,
        ]);

        $stock = app(ProductService::class)->resolveVariantStock($variant);

        $this->assertTrue($stock['is_in_stock']);
        $this->assertFalse($stock['requires_local_stock']);

        Redis::shouldReceive('watch')->once()->with("cart:user:{$user->id}");
        Redis::shouldReceive('get')->once()->with("cart:user:{$user->id}")->andReturn(null);
        Redis::shouldReceive('multi')->once();
        Redis::shouldReceive('set')->once();
        Redis::shouldReceive('expire')->once()->with("cart:user:{$user->id}", 60 * 60 * 24 * 30);
        Redis::shouldReceive('exec')->once()->andReturn([true, true]);

        $result = app(CartService::class)->addItem([
            'variant_id' => $variant->id,
            'quantity' => 3,
        ], $user->id);

        $this->assertSame(3, $result['quantity']);
    }

    public function test_invalid_dropshipping_status_transition_is_blocked(): void
    {
        $supplier = Vendor::create(['name' => 'Supplier A', 'active' => true]);
        $product = Product::factory()->create(['is_active' => true]);
        $variant = ProductVariant::factory()->for($product)->create([
            'quantity' => 0,
            'fulfillment_type' => ProductVariant::FULFILLMENT_DROPSHIPPING,
            'default_supplier_id' => $supplier->id,
            'supplier_cost' => 1000,
        ]);
        $order = \App\Models\Order::factory()->for($user = User::factory()->create(), 'user')->create();
        $item = \App\Models\OrderItem::factory()->forOrder($order)->forVariant($variant)->create([
            'fulfillment_type' => ProductVariant::FULFILLMENT_DROPSHIPPING,
            'supplier_id' => $supplier->id,
            'supplier_cost' => 1000,
            'dropship_status' => DropshipFulfillment::STATUS_PENDING,
        ]);

        $fulfillment = app(DropshippingService::class)->createFulfillmentForOrderItem($item);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(DropshippingService::class)->updateStatus($fulfillment, DropshipFulfillment::STATUS_DELIVERED);
    }

    public function test_detailed_cart_keeps_unavailable_items_visible_with_an_actionable_message(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        $variant = ProductVariant::factory()->for($product)->create([
            'quantity' => 1,
            'reserved' => 0,
            'regular_price' => 50000,
            'is_active' => true,
        ]);

        Redis::shouldReceive('get')
            ->once()
            ->with("cart:user:{$user->id}")
            ->andReturn(json_encode([
                $variant->id => [
                    'variant_id' => $variant->id,
                    'quantity' => 3,
                    'updated_at' => now()->toDateTimeString(),
                ],
            ]));

        $cart = app(CartService::class)->getDetailedCart(null, $user->id);

        $this->assertCount(1, $cart['cart']['items']);
        $this->assertTrue($cart['cart']['has_unavailable_items']);
        $this->assertSame(1, $cart['cart']['unavailable_items_count']);
        $this->assertFalse($cart['cart']['items'][0]['availability']['is_available']);
        $this->assertStringContainsString('Only 1 unit', $cart['cart']['items'][0]['availability']['message']);
        $this->assertSame(0, $cart['summary']['item_count']);
        $this->assertSame(0.0, (float) $cart['summary']['subtotal']);
    }

    public function test_featured_and_latest_collection_routes_render_focused_collection_pages(): void
    {
        $featured = Product::factory()->create(['featured' => true, 'is_active' => true, 'name' => 'Featured Product']);
        ProductVariant::factory()->for($featured)->create([
            'quantity' => 3,
            'regular_price' => 50000,
            'is_active' => true,
        ]);

        $latest = Product::factory()->create(['featured' => false, 'is_active' => true, 'name' => 'Latest Product']);
        ProductVariant::factory()->for($latest)->create([
            'quantity' => 2,
            'regular_price' => 65000,
            'is_active' => true,
        ]);

        $this->get(route('store.featured'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Storefront/Collection')
                ->where('pageTitle', 'Featured Products')
                ->where('products.data.0.name', 'Featured Product')
            );

        $this->get(route('store.latest'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Storefront/Collection')
                ->where('pageTitle', 'Latest Products')
            );
    }

    public function test_category_route_renders_only_the_selected_category_products(): void
    {
        $laptops = Category::factory()->create(['name' => 'Laptops']);
        $phones = Category::factory()->create(['name' => 'Phones']);

        $laptop = Product::factory()->create(['name' => 'Laptop Product', 'is_active' => true]);
        $phone = Product::factory()->create(['name' => 'Phone Product', 'is_active' => true]);

        $laptop->categories()->attach($laptops->id);
        $phone->categories()->attach($phones->id);

        ProductVariant::factory()->for($laptop)->create([
            'quantity' => 3,
            'regular_price' => 70000,
            'is_active' => true,
        ]);

        ProductVariant::factory()->for($phone)->create([
            'quantity' => 4,
            'regular_price' => 80000,
            'is_active' => true,
        ]);

        $this->get(route('store.category', $laptops))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Storefront/Collection')
                ->where('pageTitle', 'Laptops')
                ->where('activeCategory.name', 'Laptops')
                ->where('products.data.0.name', 'Laptop Product')
                ->missing('products.data.1')
            );
    }
}
