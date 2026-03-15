<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(
        protected DiscountService $discountService,
        protected OrderService $orderService,
        protected ProductService $productService,
    ) {}

    public function getActiveCart(?int $userId = null): ?Cart
    {
        $userId ??= Auth::id();

        if (!$userId) {
            return null;
        }

        $cart = Cart::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (!$cart) {
            $cart = Cart::create([
                'user_id' => $userId,
                'status' => 'active',
            ]);
        }

        return $this->hydrateCart($cart);
    }

    public function addItem(array $data, ?int $userId = null): CartItem
    {
        $userId = $this->ensureAuthenticatedUserId($userId);
        $quantityToAdd = max(1, (int) ($data['quantity'] ?? 1));

        return DB::transaction(function () use ($data, $userId, $quantityToAdd) {
            $cart = $this->getActiveCart($userId);
            $variant = ProductVariant::query()
                ->select(['id', 'product_id', 'sku', 'quantity', 'reserved', 'regular_price', 'sale_price', 'sale_starts_at', 'sale_ends_at'])
                ->with([
                    'product:id,name,slug',
                    'product.categories:id',
                    'product.images:id,product_id,path,is_primary,sort_order',
                    'images:id,product_variant_id,path,is_primary,sort_order',
                    'values:id,variant_type_id,value',
                    'values.type:id,name',
                ])
                ->findOrFail($data['variant_id']);

            $item = $cart->items()->where('variant_id', $variant->id)->lockForUpdate()->first();
            $newQuantity = $quantityToAdd + ($item?->quantity ?? 0);

            $available = max((int) $variant->quantity - (int) ($variant->reserved ?? 0), 0);
            if ($newQuantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => "Only {$available} unit(s) of {$variant->sku} are available.",
                ]);
            }

            if ($item) {
                $item->quantity = $newQuantity;
                $item->save();
            } else {
                $item = $cart->items()->create([
                    'variant_id' => $variant->id,
                    'quantity' => $quantityToAdd,
                ]);
            }

            return $item->load([
                'variant.product.categories:id',
                'variant.product.images:id,product_id,path,is_primary,sort_order',
                'variant.images:id,product_variant_id,path,is_primary,sort_order',
                'variant.values:id,variant_type_id,value',
                'variant.values.type:id,name',
            ]);
        });
    }

    public function updateQuantity(int $itemId, int $quantity, ?int $userId = null): ?CartItem
    {
        $userId = $this->ensureAuthenticatedUserId($userId);

        if ($quantity < 1) {
            $this->removeItem($itemId, $userId);
            return null;
        }

        return DB::transaction(function () use ($itemId, $quantity, $userId) {
            $item = CartItem::query()
                ->whereKey($itemId)
                ->whereHas('cart', fn ($query) => $query->where('user_id', $userId)->where('status', 'active'))
                ->with('variant:id,quantity,reserved,sku')
                ->lockForUpdate()
                ->firstOrFail();

            $available = max((int) $item->variant->quantity - (int) ($item->variant->reserved ?? 0), 0);

            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => "Only {$available} unit(s) of {$item->variant->sku} are available.",
                ]);
            }

            $item->quantity = $quantity;
            $item->save();

            return $item->load([
                'variant.product.categories:id',
                'variant.product.images:id,product_id,path,is_primary,sort_order',
                'variant.images:id,product_variant_id,path,is_primary,sort_order',
                'variant.values:id,variant_type_id,value',
                'variant.values.type:id,name',
            ]);
        });
    }

    public function removeItem(int $itemId, ?int $userId = null): void
    {
        $userId = $this->ensureAuthenticatedUserId($userId);

        CartItem::query()
            ->whereKey($itemId)
            ->whereHas('cart', fn ($query) => $query->where('user_id', $userId)->where('status', 'active'))
            ->firstOrFail()
            ->delete();
    }

    public function setCoupon(?string $couponCode): void
    {
        if ($couponCode) {
            session(['storefront_coupon' => Str::upper(trim($couponCode))]);
            return;
        }

        $this->clearCoupon();
    }

    public function clearCoupon(): void
    {
        session()->forget('storefront_coupon');
    }

    public function getCoupon(): ?string
    {
        $coupon = session('storefront_coupon');
        return $coupon ? (string) $coupon : null;
    }

    public function getCartCount(?int $userId = null): int
    {
        $cart = $this->getActiveCart($userId);

        return $cart ? (int) $cart->items->sum('quantity') : 0;
    }

    public function getDetailedCart(?string $couponCode = null, ?int $userId = null): array
    {
        $cart = $this->getActiveCart($userId);
        $couponCode ??= $this->getCoupon();

        if (!$cart) {
            return [
                'cart' => [
                    'id' => null,
                    'status' => 'active',
                    'items' => [],
                ],
                'summary' => [
                    'item_count' => 0,
                    'subtotal' => 0.0,
                    'discount' => 0.0,
                    'discount_id' => null,
                    'discount_label' => null,
                    'coupon' => $couponCode,
                    'shipping' => 0.0,
                    'tax' => 0.0,
                    'total' => 0.0,
                ],
                'coupon_error' => null,
            ];
        }

        $items = [];
        $discountItems = [];
        $subtotal = 0.0;

        foreach ($cart->items as $item) {
            $variant = $item->variant;
            if (!$variant || !$variant->product) {
                continue;
            }

            $categoryIds = $variant->product->categories?->pluck('id')->all() ?? [];
            $pricing = $this->productService->resolveVariantPricing($variant);
            $stock = $this->productService->resolveVariantStock($variant);
            $lineTotal = round(((float) $pricing['current']) * (int) $item->quantity, 2);
            $subtotal += $lineTotal;

            $variantLabel = $variant->values
                ? $variant->values->map(fn ($value) => trim(($value->type?->name ? $value->type->name . ': ' : '') . $value->value))->implode(' / ')
                : $variant->sku;

            $items[] = [
                'id' => (int) $item->id,
                'quantity' => (int) $item->quantity,
                'subtotal' => $lineTotal,
                'category_ids' => $categoryIds,
                'product' => [
                    'id' => (int) $variant->product->id,
                    'name' => $variant->product->name,
                    'slug' => $variant->product->slug,
                    'image' => $this->productService->resolveProductImage($variant->product, $variant),
                ],
                'variant' => [
                    'id' => (int) $variant->id,
                    'sku' => $variant->sku,
                    'label' => $variantLabel ?: $variant->sku,
                    'image' => $this->productService->resolveProductImage($variant->product, $variant),
                    'price' => $pricing,
                    'stock' => $stock,
                ],
            ];

            $discountItems[] = [
                'variant_id' => (int) $variant->id,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $pricing['current'],
                'product_id' => (int) $variant->product->id,
                'category_ids' => $categoryIds,
            ];
        }

        $user = Auth::id() ? User::find(Auth::id()) : null;

        $discount = [
            'discount_id' => null,
            'label' => null,
            'amount' => 0.0,
        ];
        $couponError = null;

        if (!empty($discountItems)) {
            try {
                $discount = $this->discountService->previewQuote(
                    user: $user,
                    items: $discountItems,
                    subtotal: round($subtotal, 2),
                    shippingTotal: 0.0,
                    taxTotal: 0.0,
                    channel: 'online',
                    couponCode: $couponCode,
                );
            } catch (ValidationException $exception) {
                $couponError = collect($exception->errors())->flatten()->first() ?: 'The coupon could not be applied.';
            }
        }

        $discountAmount = round((float) ($discount['amount'] ?? 0), 2);
        $total = max(round($subtotal - $discountAmount, 2), 0);

        return [
            'cart' => [
                'id' => (int) $cart->id,
                'status' => $cart->status,
                'items' => $items,
            ],
            'summary' => [
                'item_count' => (int) collect($items)->sum('quantity'),
                'subtotal' => round($subtotal, 2),
                'discount' => $discountAmount,
                'discount_id' => $discount['discount_id'] ?? null,
                'discount_label' => $discount['label'] ?? null,
                'coupon' => $couponCode,
                'shipping' => 0.0,
                'tax' => 0.0,
                'total' => $total,
            ],
            'coupon_error' => $couponError,
        ];
    }

    public function checkout(array $payload = [], ?int $userId = null): Order
    {
        $userId = $this->ensureAuthenticatedUserId($userId);
        $cartData = $this->getDetailedCart($payload['coupon'] ?? $this->getCoupon(), $userId);

        if (empty($cartData['cart']['items'])) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        if (!empty($cartData['coupon_error'])) {
            throw ValidationException::withMessages([
                'coupon' => $cartData['coupon_error'],
            ]);
        }

        $checkoutItems = collect($cartData['cart']['items'])
            ->map(fn (array $item) => [
                'variant_id' => (int) $item['variant']['id'],
                'quantity' => (int) $item['quantity'],
                'price' => (float) $item['variant']['price']['current'],
            ])
            ->values()
            ->all();

        $snapshotItems = collect($cartData['cart']['items'])
            ->map(fn (array $item) => [
                'variant_id' => (int) $item['variant']['id'],
                'quantity' => (float) $item['quantity'],
                'unit_price' => (float) $item['variant']['price']['current'],
                'product_id' => (int) $item['product']['id'],
                'category_ids' => $item['category_ids'] ?? [],
            ])
            ->values()
            ->all();

        $discountAmount = (float) $cartData['summary']['discount'];
        $subtotal = (float) $cartData['summary']['subtotal'];
        $couponCode = $payload['coupon'] ?? $cartData['summary']['coupon'];

        $token = hash_hmac('sha256', Str::uuid()->toString() . now()->timestamp, (string) config('app.key'));

        DB::table('checkout_sessions')->insert([
            'token' => $token,
            'user_id' => $userId,
            'items' => json_encode($snapshotItems),
            'subtotal' => round($subtotal, 2),
            'shipping_total' => 0,
            'discount_amount' => round($discountAmount, 2),
            'discount_id' => $cartData['summary']['discount_id'] ?? null,
            'discount_snapshot' => json_encode([
                'discount_id' => $cartData['summary']['discount_id'] ?? null,
                'code' => $couponCode,
                'label' => $cartData['summary']['discount_label'],
                'amount' => round($discountAmount, 2),
            ]),
            'total' => round((float) $cartData['summary']['total'], 2),
            'channel' => 'online',
            'used' => false,
            'expires_at' => now()->addMinutes(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = $this->orderService->handle([
            'customer_id' => $userId,
            'channel' => 'online',
            'items' => $checkoutItems,
            'subtotal' => round($subtotal, 2),
            'tax_total' => 0,
            'coupon' => $couponCode,
            'payment_method' => $payload['payment_method'] ?? 'cash',
            'checkout_token' => $token,
        ]);

        $activeCart = $this->getActiveCart($userId);
        if ($activeCart) {
            $activeCart->status = 'converted';
            $activeCart->save();
            $activeCart->items()->delete();
        }

        $this->clearCoupon();

        return $order;
    }

    protected function hydrateCart(Cart $cart): Cart
    {
        return $cart->load([
            'items.variant.product.categories:id',
            'items.variant.product.images:id,product_id,path,is_primary,sort_order',
            'items.variant.images:id,product_variant_id,path,is_primary,sort_order',
            'items.variant.values:id,variant_type_id,value',
            'items.variant.values.type:id,name',
        ]);
    }

    protected function ensureAuthenticatedUserId(?int $userId = null): int
    {
        $userId ??= Auth::id();

        if (!$userId) {
            throw ValidationException::withMessages([
                'auth' => 'Please sign in to manage your cart.',
            ]);
        }

        return $userId;
    }
}
