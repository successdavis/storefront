<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductNoteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_notes_can_be_created_updated_and_deleted(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();
        $service = app(ProductService::class);

        $note = $service->storeAdminNote($product, $user, 'First note');

        $this->assertNotNull($note);
        $this->assertDatabaseHas('product_notes', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'note' => 'First note',
        ]);

        $updated = $service->updateAdminNote($note, 'Updated note');

        $this->assertSame('Updated note', $updated?->note);
        $this->assertDatabaseHas('product_notes', [
            'id' => $note->id,
            'note' => 'Updated note',
        ]);

        $service->deleteAdminNote($note->fresh());

        $this->assertDatabaseMissing('product_notes', [
            'id' => $note->id,
        ]);
    }
}
