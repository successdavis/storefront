<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductService;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DiscountPricingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_level_line_item_discount_wins_over_category_and_global_rules(): void
    {
        $category = Category::factory()->create(['name' => 'Audio']);
        $product = Product::factory()->create(['name' => 'Studio Headphones']);
        $product->categories()->attach($category->id);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'regular_price' => 100,
            'sale_starts_at' => null,
            'sale_ends_at' => null,
        ]);

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

        $categoryDiscount = Discount::query()->create([
            'name' => 'Category 15',
            'description' => 'Category automatic discount',
            'code' => null,
            'type' => Discount::TYPE_PERCENTAGE,
            'value' => 15,
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
        $categoryDiscount->categories()->attach($category->id);

        $productDiscount = Discount::query()->create([
            'name' => 'Product 10',
            'description' => 'Product automatic discount',
            'code' => null,
            'type' => Discount::TYPE_PERCENTAGE,
            'value' => 10,
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
        $productDiscount->products()->attach($product->id);

        $pricing = app(ProductService::class)->resolveVariantPricing(
            $variant->fresh()->load('product.categories'),
            null,
            $product->fresh('categories'),
        );

        $this->assertSame(100.0, $pricing['regular']);
        $this->assertSame(90.0, $pricing['current']);
        $this->assertTrue($pricing['has_discount']);
        $this->assertSame('automatic', $pricing['discount_source']);
        $this->assertSame('Product 10', $pricing['discount_label']);
    }

    public function test_storefront_product_page_and_checkout_preview_use_the_same_discounted_unit_price(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([RoleNames::CUSTOMER]);

        $category = Category::factory()->create(['name' => 'Gaming']);
        $product = Product::factory()->create(['name' => 'Game Console']);
        $product->categories()->attach($category->id);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'regular_price' => 100,
            'sale_starts_at' => null,
            'sale_ends_at' => null,
        ]);

        $automatic = Discount::query()->create([
            'name' => 'Console Price Drop',
            'description' => 'Automatic product markdown',
            'code' => null,
            'type' => Discount::TYPE_PERCENTAGE,
            'value' => 10,
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
        $automatic->products()->attach($product->id);

        $coupon = Discount::query()->create([
            'name' => 'SAVE5',
            'description' => 'Checkout coupon',
            'code' => 'SAVE5',
            'type' => Discount::TYPE_PERCENTAGE,
            'value' => 5,
            'application_method' => Discount::APPLICATION_ORDER_TOTAL,
            'min_order_amount' => null,
            'usage_limit' => null,
            'usage_limit_per_user' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
            'customer_scope' => Discount::CUSTOMER_SCOPE_ALL,
            'priority' => 0,
        ]);
        $coupon->products()->attach($product->id);

        $this->actingAs($user)
            ->get(route('store.product', $product->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Storefront/Product')
                ->where('product.price.regular', 100)
                ->where('product.price.current', 90)
                ->where('product.price.has_discount', true)
            );

        $previewResponse = $this->actingAs($user)->postJson(route('checkout.preview'), [
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 1],
            ],
            'coupon' => 'SAVE5',
            'channel' => 'online',
        ]);

        $previewResponse
            ->assertOk()
            ->assertJson([
                'subtotal' => 90,
                'discount' => 4.5,
                'total' => 85.5,
            ]);

        $session = \App\Models\CheckoutSession::query()->latest('id')->firstOrFail();

        $this->assertSame(90.0, (float) $session->subtotal);
        $this->assertSame(4.5, (float) $session->discount_amount);
        $this->assertSame(85.5, (float) $session->total);
        $this->assertSame(90.0, (float) $session->items[0]['unit_price']);
        $this->assertSame(4.5, (float) $session->discount_snapshot['amount']);
    }
}
