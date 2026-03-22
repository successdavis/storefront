<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class CartService
{
    protected int $redisTtlSeconds = 60 * 60 * 24 * 30; // 30 days

    public function __construct(
        protected DiscountService $discountService,
        protected OrderService $orderService,
        protected ProductService $productService,
    ) {}

    /* ===========================
       Redis helpers
       =========================== */

    protected function redisKey(int $userId): string
    {
        return "cart:user:{$userId}";
    }

    protected function getRedisCartArray(int $userId): array
    {
        $raw = Redis::get($this->redisKey($userId));
        if (!$raw) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function persistRedisCartArray(int $userId, array $cart): void
    {
        $key = $this->redisKey($userId);
        Redis::set($key, json_encode($cart));
        Redis::expire($key, $this->redisTtlSeconds);
    }

    /* ===========================
       Public API (fast Redis first)
       =========================== */

    public function addItem(array $data, ?int $userId = null): array
    {
        $userId = $this->ensureAuthenticatedUserId($userId);
        $quantityToAdd = max(1, (int) ($data['quantity'] ?? 1));
        $variantId = (int) $data['variant_id'];

        // Load variant from cache / DB (same select as before)
        $variant = Cache::remember(
            "variant:{$variantId}",
            3600,
            function () use ($variantId) {
                return ProductVariant::query()
                    ->select([
                        'id',
                        'product_id',
                        'sku',
                        'quantity',
                        'reserved',
                        'regular_price',
                        'sale_price',
                        'sale_starts_at',
                        'sale_ends_at'
                    ])
                    ->with([
                        'product:id,name,slug',
                        'product.categories:id',
                        'product.images:id,product_id,path,is_primary,sort_order',
                        'images:id,product_variant_id,path,is_primary,sort_order',
                        'values:id,variant_type_id,value',
                        'values.type:id,name',
                    ])
                    ->findOrFail($variantId);
            }
        );

        $available = max((int) $variant->quantity - (int) ($variant->reserved ?? 0), 0);

        $key = $this->redisKey($userId);

        // Use WATCH/MULTI/EXEC retry loop to avoid lost updates
        $attempts = 0;
        $maxAttempts = 5;

        do {
            $attempts++;
            Redis::watch($key);

            $cart = $this->getRedisCartArray($userId);

            $currentQty = isset($cart[$variantId]) ? (int) $cart[$variantId]['quantity'] : 0;
            $newQuantity = $currentQty + $quantityToAdd;

            if ($newQuantity > $available) {
                Redis::unwatch();
                throw ValidationException::withMessages([
                    'quantity' => "Only {$available} unit(s) of {$variant->sku} are available.",
                ]);
            }

            // Update structure
            $cart[$variantId] = [
                'variant_id' => $variantId,
                'quantity' => $newQuantity,
                'updated_at' => Carbon::now()->toDateTimeString(),
            ];

            Redis::multi();
            Redis::set($key, json_encode($cart));
            Redis::expire($key, $this->redisTtlSeconds);
            $results = Redis::exec();

            if ($results !== null) {
                // success, break
                break;
            }

            // otherwise retry
        } while ($attempts < $maxAttempts);

        if ($results === null) {
            // rare: concurrent race couldn't complete
            throw ValidationException::withMessages([
                'cart' => 'Could not update cart due to high concurrency. Please try again.',
            ]);
        }

        // Return a lightweight representation (no DB model write)
        return [
            'variant_id' => $variantId,
            'quantity' => $newQuantity,
        ];
    }

    public function updateQuantity(int $variantId, int $quantity, ?int $userId = null): ?array
    {
        $userId = $this->ensureAuthenticatedUserId($userId);

        if ($quantity < 1) {
            $this->removeItem($variantId, $userId);
            return null;
        }

        // re-check availability
        $variant = Cache::remember(
            "variant:{$variantId}",
            3600,
            fn() => ProductVariant::select('id', 'quantity', 'reserved', 'sku')->findOrFail($variantId)
        );

        $available = max((int) $variant->quantity - (int) ($variant->reserved ?? 0), 0);

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$available} unit(s) of {$variant->sku} are available.",
            ]);
        }

        $key = $this->redisKey($userId);
        $attempts = 0;
        $maxAttempts = 5;
        do {
            $attempts++;
            Redis::watch($key);
            $cart = $this->getRedisCartArray($userId);
            if (!isset($cart[$variantId])) {
                Redis::unwatch();
                throw ValidationException::withMessages([
                    'item' => 'Cart item not found.',
                ]);
            }

            $cart[$variantId]['quantity'] = $quantity;
            $cart[$variantId]['updated_at'] = Carbon::now()->toDateTimeString();

            Redis::multi();
            Redis::set($key, json_encode($cart));
            Redis::expire($key, $this->redisTtlSeconds);
            $results = Redis::exec();

            if ($results !== null) {
                break;
            }
        } while ($attempts < $maxAttempts);

        if ($results === null) {
            throw ValidationException::withMessages([
                'cart' => 'Could not update cart due to high concurrency. Please try again.',
            ]);
        }

        return [
            'variant_id' => $variantId,
            'quantity' => $quantity,
        ];
    }

    public function removeItem(int $variantId, ?int $userId = null): void
    {
        $userId = $this->ensureAuthenticatedUserId($userId);

        $key = $this->redisKey($userId);
        $attempts = 0;
        $maxAttempts = 5;
        do {
            $attempts++;
            Redis::watch($key);
            $cart = $this->getRedisCartArray($userId);

            if (!isset($cart[$variantId])) {
                Redis::unwatch();
                return; // already gone
            }

            unset($cart[$variantId]);

            Redis::multi();
            Redis::set($key, json_encode($cart));
            Redis::expire($key, $this->redisTtlSeconds);
            $results = Redis::exec();

            if ($results !== null) {
                break;
            }
        } while ($attempts < $maxAttempts);

        if ($results === null) {
            throw ValidationException::withMessages([
                'cart' => 'Could not remove item due to high concurrency. Please try again.',
            ]);
        }
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
        $userId = Auth::id() ?? $userId;
        if (!$userId) {
            return 0;
        }

        $cart = $this->getRedisCartArray($userId);
        return (int) array_sum(array_map(fn($it) => (int)$it['quantity'], $cart));
    }

    /**
     * Builds the detailed cart payload (same shape as before) using Redis as source.
     */
    public function getDetailedCart(?string $couponCode = null, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        $couponCode ??= $this->getCoupon();

        if (!$userId) {
            // no user authenticated — return empty structure
            return $this->emptyDetailedCartPayload($couponCode);
        }

        $redisCart = $this->getRedisCartArray($userId);
        if (empty($redisCart)) {
            return $this->emptyDetailedCartPayload($couponCode);
        }

        $variantIds = array_map('intval', array_keys($redisCart));
        $variants = ProductVariant::query()
            ->with([
                'product:id,name,slug',
                'product.categories:id',
                'product.images:id,product_id,path,is_primary,sort_order',
                'images:id,product_variant_id,path,is_primary,sort_order',
                'values:id,variant_type_id,value',
                'values.type:id,name',
            ])
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $user = $userId ? User::find($userId) : null;
        $items = [];
        $discountItems = [];
        $subtotal = 0.0;

        foreach ($redisCart as $vid => $entry) {
            $vid = (int) $vid;
            $quantity = max(0, (int) ($entry['quantity'] ?? 0));
            if ($quantity < 1) {
                continue;
            }

            $variant = $variants[$vid] ?? null;
            if (!$variant || !$variant->product) {
                continue;
            }

            $categoryIds = $variant->product->categories?->pluck('id')->all() ?? [];
            $pricing = $this->productService->resolveVariantPricing($variant, $user, $variant->product);
            $stock = $this->productService->resolveVariantStock($variant);
            $lineTotal = round(((float) $pricing['current']) * $quantity, 2);
            $subtotal += $lineTotal;

            $variantLabel = $variant->values
                ? $variant->values->map(fn ($value) => trim(($value->type?->name ? $value->type->name . ': ' : '') . $value->value))->implode(' / ')
                : $variant->sku;

            $items[] = [
                'id' => null, // there is no DB CartItem id yet
                'variant_id' => (int) $variant->id,
                'quantity' => (int) $quantity,
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
                'quantity' => (float) $quantity,
                'unit_price' => (float) $pricing['current'],
                'product_id' => (int) $variant->product->id,
                'category_ids' => $categoryIds,
            ];
        }

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
                'id' => null, // no DB cart id until persisted at checkout
                'status' => 'active',
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

    protected function emptyDetailedCartPayload(?string $couponCode = null): array
    {
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

    /**
     * Checkout: persist session and cart snapshot to DB, call order service, then clear Redis cart.
     */
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

        // Build checkout items (order input) and snapshot items (for persistence)
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

        return DB::transaction(function () use ($userId, $checkoutItems, $snapshotItems, $discountAmount, $subtotal, $couponCode, $token, $payload) {
            // Insert checkout session (same as before)
            DB::table('checkout_sessions')->insert([
                'token' => $token,
                'user_id' => $userId,
                'items' => json_encode($snapshotItems),
                'subtotal' => round($subtotal, 2),
                'shipping_total' => 0,
                'discount_amount' => round($discountAmount, 2),
                'discount_id' => null,
                'discount_snapshot' => json_encode([
                    'discount_id' => null,
                    'code' => $couponCode,
                    'label' => null,
                    'amount' => round($discountAmount, 2),
                ]),
                'total' => round((float) ($subtotal - $discountAmount), 2),
                'channel' => 'online',
                'used' => false,
                'expires_at' => now()->addMinutes(30),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Call order service
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

            // Persist the converted cart snapshot in DB for audit / history
            $cartModel = Cart::create([
                'user_id' => $userId,
                'status' => 'converted',
            ]);

            foreach ($snapshotItems as $snap) {
                $cartModel->items()->create([
                    'variant_id' => $snap['variant_id'],
                    'quantity' => (int) $snap['quantity'],
                    'price_snapshot' => $snap['unit_price'] ?? null,
                ]);
            }

            // Clear redis cart & coupon
            Redis::del($this->redisKey($userId));
            $this->clearCoupon();

            return $order;
        });
    }

    /* ===========================
       Utility / compatibility
       =========================== */

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

    public function clearCart(int $userId): void
    {
        Redis::del("cart:user:{$userId}");
    }
}
