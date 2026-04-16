<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Product\StoreProductNoteRequest;
use App\Http\Requests\Admin\Product\UpdateProductNoteRequest;
use App\Models\Product;
use App\Models\ProductNote;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;

class ProductNoteController extends Controller
{
    public function __construct(protected ProductService $productService) {}

    public function store(StoreProductNoteRequest $request, Product $product): RedirectResponse
    {
        $note = $this->productService->storeAdminNote(
            $product,
            $request->user(),
            (string) $request->validated('note'),
        );

        if (!$note) {
            return back()->with('error', 'Product notes are unavailable until the latest database migrations are run.');
        }

        return back()->with('success', 'Product note added.');
    }

    public function update(UpdateProductNoteRequest $request, Product $product, ProductNote $note): RedirectResponse
    {
        abort_unless((int) $note->product_id === (int) $product->id, 404);

        $updated = $this->productService->updateAdminNote($note, (string) $request->validated('note'));

        if (!$updated) {
            return back()->with('error', 'Product notes are unavailable until the latest database migrations are run.');
        }

        return back()->with('success', 'Product note updated.');
    }

    public function destroy(Product $product, ProductNote $note): RedirectResponse
    {
        abort_unless((int) $note->product_id === (int) $product->id, 404);

        if (!$this->productService->deleteAdminNote($note)) {
            return back()->with('error', 'Product notes are unavailable until the latest database migrations are run.');
        }

        return back()->with('success', 'Product note deleted.');
    }
}
