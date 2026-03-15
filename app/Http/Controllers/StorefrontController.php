<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Services\CartService;
use App\Services\StorefrontService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function __construct(
        protected StorefrontService $storefrontService,
        protected CartService $cartService,
    ) {}

    public function home(Request $request): Response
    {
        return Inertia::render('Storefront/Home', $this->storefrontService->homeData(
            $request->only(['q', 'category', 'per_page'])
        ));
    }

    public function search(Request $request): Response
    {
        return Inertia::render('Storefront/Home', $this->storefrontService->homeData(array_merge(
            $request->only(['per_page', 'category']),
            ['q' => $request->string('q')->toString()]
        )));
    }

    public function category(Request $request, Category $category): Response
    {
        return Inertia::render('Storefront/Home', $this->storefrontService->categoryData(
            category: $category,
            filters: $request->only(['q', 'per_page'])
        ));
    }

    public function product(Product $product): Response
    {
        return Inertia::render('Storefront/Product', $this->storefrontService->productData($product));
    }

    public function cart(Request $request): Response
    {
        $coupon = $request->query('coupon');

        return Inertia::render('Storefront/Cart', $this->storefrontService->cartData(
            $coupon !== null ? (string) $coupon : null
        ));
    }

    public function addToCart(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->cartService->addItem($validated);

        return back()->with('success', 'Item added to cart successfully.');
    }

    public function updateCartItem(Request $request, CartItem $cartItem): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $this->cartService->updateQuantity((int) $cartItem->id, (int) $validated['quantity']);

        return back()->with('success', 'Cart updated.');
    }

    public function removeCartItem(CartItem $cartItem): RedirectResponse
    {
        $this->cartService->removeItem((int) $cartItem->id);

        return back()->with('success', 'Item removed from cart.');
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'coupon' => ['nullable', 'string', 'max:64'],
        ]);

        $coupon = isset($validated['coupon']) ? trim((string) $validated['coupon']) : null;
        $this->cartService->setCoupon($coupon !== '' ? $coupon : null);

        $cart = $this->cartService->getDetailedCart($coupon);
        if (!empty($cart['coupon_error'])) {
            $this->cartService->clearCoupon();
            return back()->with('error', $cart['coupon_error']);
        }

        if ($coupon) {
            return back()->with('success', 'Coupon applied successfully.');
        }

        return back()->with('success', 'Coupon removed.');
    }

    public function checkout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['nullable', 'string', 'max:32'],
            'coupon' => ['nullable', 'string', 'max:64'],
        ]);

        $order = $this->cartService->checkout($validated);

        return redirect()
            ->route('store.cart')
            ->with('success', "Order {$order->order_number} placed successfully.");
    }
}
