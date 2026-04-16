<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductVariantHistoryGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_durable_history_tolerates_missing_optional_history_tables(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'quantity' => 0,
        ]);

        Schema::dropIfExists('sale_items');

        $this->assertFalse($variant->fresh()->hasDurableHistory());
    }
}
