<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
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
}
