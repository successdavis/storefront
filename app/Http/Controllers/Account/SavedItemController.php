<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\CustomerSavedItem;
use App\Services\CustomerSavedItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedItemController extends Controller
{
    public function __construct(
        protected CustomerSavedItemService $savedItemService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CustomerSavedItem::class);

        $listType = (string) $request->route('listType');
        $savedItems = $this->savedItemService->paginate(
            $request->user(),
            $listType,
            12,
        );

        return Inertia::render('Account/SavedItems/Index', [
            'listType' => $listType,
            'savedItems' => $savedItems,
            'counts' => $this->savedItemService->counts($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CustomerSavedItem::class);

        $listType = (string) $request->route('listType');
        $validated = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->savedItemService->addVariant(
            $request->user(),
            (int) $validated['variant_id'],
            $listType,
            (int) ($validated['quantity'] ?? 1),
        );

        return back()->with('success', $listType === CustomerSavedItem::TYPE_WISHLIST
            ? 'Item added to your wishlist.'
            : 'Item saved for later.');
    }

    public function destroy(Request $request, CustomerSavedItem $savedItem): RedirectResponse
    {
        $this->authorize('delete', $savedItem);
        $this->savedItemService->remove($request->user(), $savedItem);

        return back()->with('success', 'Saved item removed.');
    }

    public function moveToCart(Request $request, CustomerSavedItem $savedItem): RedirectResponse
    {
        $this->authorize('update', $savedItem);

        try {
            $this->savedItemService->moveSavedItemToCart($request->user(), $savedItem);

            return back()->with('success', 'Item moved to cart.');
        } catch (\Throwable $exception) {
            if (method_exists($exception, 'errors')) {
                return back()->withErrors($exception->errors())->with('error', collect($exception->errors())->flatten()->first());
            }

            throw $exception;
        }
    }

    public function moveToList(Request $request, CustomerSavedItem $savedItem): RedirectResponse
    {
        $this->authorize('update', $savedItem);

        $targetListType = (string) $request->route('targetListType');
        $this->savedItemService->moveBetweenLists($request->user(), $savedItem, $targetListType);

        return back()->with('success', 'Saved item updated.');
    }
}
