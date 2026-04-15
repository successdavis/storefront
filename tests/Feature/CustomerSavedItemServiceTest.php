<?php

namespace Tests\Feature;

use App\Models\CustomerSavedItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CustomerSavedItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSavedItemServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_paginate_saved_items_with_variant_relationships(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()
            ->for($product)
            ->create([
                'quantity' => 5,
                'regular_price' => 25000,
            ]);

        CustomerSavedItem::query()->create([
            'user_id' => $user->id,
            'list_type' => CustomerSavedItem::TYPE_SAVED_FOR_LATER,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
            'price_snapshot' => 25000,
            'currency' => 'NGN',
            'product_name_snapshot' => $product->name,
            'variant_label_snapshot' => $variant->sku,
            'meta' => [],
        ]);

        $paginator = app(CustomerSavedItemService::class)->paginate(
            $user,
            CustomerSavedItem::TYPE_SAVED_FOR_LATER,
            6,
        );

        $items = $paginator->items();

        $this->assertCount(1, $items);
        $this->assertSame($product->id, $items[0]['product']['id']);
        $this->assertSame($variant->id, $items[0]['variant']['id']);
        $this->assertTrue($items[0]['availability']['is_available']);
    }
}
