<?php

namespace App\Services;

use App\Models\CustomerSavedItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CustomerSavedItemService
{
    public function __construct(
        protected CartService $cartService,
        protected ProductService $productService,
    ) {}

    public function paginate(User $user, string $listType, int $perPage = 12): LengthAwarePaginator
    {
        $this->assertValidListType($listType);

        $paginator = CustomerSavedItem::query()
            ->where('user_id', $user->id)
            ->where('list_type', $listType)
            ->with([
                'variant' => fn ($query) => $query
                    ->withTrashed()
                    ->with([
                        'product' => fn ($productQuery) => $productQuery->withTrashed()->with('images'),
                        'values.type',
                        'images',
                    ]),
            ])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (CustomerSavedItem $item) => $this->toPayload($item))
        );

        return $paginator;
    }

    public function addVariant(User $user, int $variantId, string $listType, int $quantity = 1): CustomerSavedItem
    {
        $this->assertValidListType($listType);

        $variant = $this->loadVariant($variantId);
        $quantity = max(1, $quantity);
        $pricing = $this->productService->resolveVariantPricing($variant, $user, $variant->product);
        $variantLabel = $variant->values
            ->map(fn ($value) => trim(($value->type?->name ? $value->type->name . ': ' : '') . $value->value))
            ->implode(' / ');

        $item = CustomerSavedItem::query()->firstOrNew([
            'user_id' => $user->id,
            'list_type' => $listType,
            'variant_id' => $variant->id,
        ]);

        $item->fill([
            'product_id' => $variant->product?->id,
            'quantity' => $listType === CustomerSavedItem::TYPE_WISHLIST
                ? 1
                : max((int) $item->quantity, $quantity),
            'price_snapshot' => $pricing['current'],
            'currency' => 'NGN',
            'product_name_snapshot' => $variant->product?->name,
            'variant_label_snapshot' => $variantLabel ?: $variant->sku,
            'meta' => [
                'saved_at_stock' => $this->productService->resolveVariantStock($variant),
            ],
        ]);

        $item->save();

        return $item->fresh(['variant.product.images', 'variant.values.type', 'variant.images']);
    }

    public function remove(User $user, CustomerSavedItem $item): void
    {
        $this->assertOwnedByUser($user, $item);
        $item->delete();
    }

    public function moveBetweenLists(User $user, CustomerSavedItem $item, string $targetListType): CustomerSavedItem
    {
        $this->assertOwnedByUser($user, $item);
        $this->assertValidListType($targetListType);

        if ($item->list_type === $targetListType) {
            return $item;
        }

        $target = CustomerSavedItem::query()->firstOrNew([
            'user_id' => $user->id,
            'list_type' => $targetListType,
            'variant_id' => $item->variant_id,
        ]);

        $target->fill([
            'product_id' => $item->product_id,
            'quantity' => $targetListType === CustomerSavedItem::TYPE_WISHLIST
                ? 1
                : max((int) $target->quantity, (int) $item->quantity),
            'price_snapshot' => $item->price_snapshot,
            'currency' => $item->currency,
            'product_name_snapshot' => $item->product_name_snapshot,
            'variant_label_snapshot' => $item->variant_label_snapshot,
            'meta' => $item->meta,
        ]);

        $target->save();
        $item->delete();

        return $target->fresh(['variant.product.images', 'variant.values.type', 'variant.images']);
    }

    public function moveSavedItemToCart(User $user, CustomerSavedItem $item): void
    {
        $this->assertOwnedByUser($user, $item);

        if (!$item->variant_id) {
            throw ValidationException::withMessages([
                'item' => 'This saved item can no longer be moved to cart.',
            ]);
        }

        $variant = $this->loadVariant((int) $item->variant_id);

        if (!$variant->product || !$variant->product->is_active || !$variant->is_active || $variant->trashed()) {
            throw ValidationException::withMessages([
                'item' => 'This product is no longer available.',
            ]);
        }

        $stock = $this->productService->resolveVariantStock($variant);
        $desiredQuantity = max(1, (int) $item->quantity);

        if (!$stock['is_in_stock'] || $stock['available'] < $desiredQuantity) {
            throw ValidationException::withMessages([
                'item' => "Only {$stock['available']} unit(s) are currently available for this item.",
            ]);
        }

        $this->cartService->addItem([
            'variant_id' => $variant->id,
            'quantity' => $desiredQuantity,
        ], $user->id);

        $item->delete();
    }

    public function moveCartItemToSavedForLater(User $user, int $variantId): CustomerSavedItem
    {
        $cartData = $this->cartService->getDetailedCart(null, $user->id);
        $cartItem = collect($cartData['cart']['items'] ?? [])
            ->firstWhere('variant_id', $variantId);

        if (!$cartItem) {
            throw ValidationException::withMessages([
                'item' => 'Cart item not found.',
            ]);
        }

        $savedItem = $this->addVariant(
            $user,
            $variantId,
            CustomerSavedItem::TYPE_SAVED_FOR_LATER,
            (int) ($cartItem['quantity'] ?? 1),
        );

        $this->cartService->removeItem($variantId, $user->id);

        return $savedItem;
    }

    public function counts(User $user): array
    {
        $counts = CustomerSavedItem::query()
            ->where('user_id', $user->id)
            ->selectRaw('list_type, count(*) as total')
            ->groupBy('list_type')
            ->pluck('total', 'list_type');

        return [
            CustomerSavedItem::TYPE_WISHLIST => (int) ($counts[CustomerSavedItem::TYPE_WISHLIST] ?? 0),
            CustomerSavedItem::TYPE_SAVED_FOR_LATER => (int) ($counts[CustomerSavedItem::TYPE_SAVED_FOR_LATER] ?? 0),
        ];
    }

    public function listTypes(): array
    {
        return [
            CustomerSavedItem::TYPE_WISHLIST,
            CustomerSavedItem::TYPE_SAVED_FOR_LATER,
        ];
    }

    public function toPayload(CustomerSavedItem $item): array
    {
        $variant = $item->variant;
        $product = $variant?->product;
        $stock = $variant ? $this->productService->resolveVariantStock($variant) : null;
        $pricing = $variant ? $this->productService->resolveVariantPricing($variant) : null;
        $isUnavailable = !$variant || !$product || !$product->is_active || !$variant->is_active || $variant->trashed();

        $message = null;
        if ($isUnavailable) {
            $message = 'This product is no longer available.';
        } elseif ($stock && !$stock['is_in_stock']) {
            $message = 'Currently out of stock.';
        } elseif ($stock && $stock['available'] < (int) $item->quantity) {
            $message = "Only {$stock['available']} unit(s) are currently available.";
        }

        return [
            'id' => (int) $item->id,
            'list_type' => $item->list_type,
            'quantity' => (int) $item->quantity,
            'saved_at' => optional($item->created_at)?->toIso8601String(),
            'snapshot' => [
                'price' => $item->price_snapshot !== null ? (float) $item->price_snapshot : null,
                'currency' => $item->currency,
                'product_name' => $item->product_name_snapshot,
                'variant_label' => $item->variant_label_snapshot,
            ],
            'availability' => [
                'is_available' => !$isUnavailable && (bool) ($stock['is_in_stock'] ?? false),
                'message' => $message,
                'stock' => $stock,
            ],
            'product' => [
                'id' => $product?->id,
                'name' => $product?->name ?? $item->product_name_snapshot,
                'slug' => $product?->slug,
                'image' => $product && $variant ? $this->productService->resolveProductImage($product, $variant) : null,
            ],
            'variant' => [
                'id' => $variant?->id,
                'sku' => $variant?->sku,
                'label' => $item->variant_label_snapshot,
                'price' => $pricing,
            ],
        ];
    }

    protected function assertValidListType(string $listType): void
    {
        if (!in_array($listType, $this->listTypes(), true)) {
            throw ValidationException::withMessages([
                'list_type' => 'Invalid saved item list.',
            ]);
        }
    }

    protected function assertOwnedByUser(User $user, CustomerSavedItem $item): void
    {
        if ((int) $item->user_id !== (int) $user->id) {
            abort(403);
        }
    }

    protected function loadVariant(int $variantId): ProductVariant
    {
        return ProductVariant::query()
            ->withTrashed()
            ->with([
                'product' => fn ($query) => $query->withTrashed()->with(['images', 'categories:id']),
                'values.type',
                'images',
            ])
            ->findOrFail($variantId);
    }
}
