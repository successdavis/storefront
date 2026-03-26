<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\ProductService;
use App\Support\PermissionNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService,
        protected CartService $cartService,
        protected ProductService $productService,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $params = $request->validate($this->selectionRules($request));

        return Inertia::render('Checkout/Index', $this->checkoutService->getCheckoutData($request->user(), $params));
    }

    public function applyDiscount(Request $request): RedirectResponse
    {
        $data = $request->validate($this->selectionRules($request));

        return redirect()->route('checkout.index', $this->selectionRouteParams($data));
    }

    public function initializePayment(Request $request): HttpResponse
    {
        $data = $request->validate($this->selectionRules($request));

        try {
            $paymentData = $this->checkoutService->initializePayment($request->user(), $data);

            return Inertia::location((string) $paymentData['authorization_url']);
        } catch (ValidationException $exception) {
            $firstError = collect($exception->errors())->flatten()->first();

            return redirect()
                ->route('checkout.index', $this->selectionRouteParams($data))
                ->withErrors($exception->errors())
                ->with('error', $firstError);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Unable to initialize payment at the moment. Please try again.');
        }
    }

    public function verifyPayment(Request $request): RedirectResponse
    {
        $reference = (string) ($request->query('reference') ?: $request->query('trxref'));

        try {
            $result = $this->checkoutService->verifyPayment($request->user(), $reference);

            if (!$result['success']) {
                Log::warning('Payment verification failed', [
                    'user_id' => $request->user()?->id,
                    'reference' => $reference,
                    'message' => $result['message'],
                ]);

                return redirect()
                    ->route('checkout.index')
                    ->with('error', $result['message']);
            }

            return redirect()
                ->route('order.success', ['order' => $result['order']->id])
                ->with('success', $result['message']);
        } catch (ValidationException $exception) {
            Log::notice('Payment validation error', [
                'user_id' => $request->user()?->id,
                'reference' => $reference,
                'errors' => $exception->errors(),
            ]);

            return redirect()
                ->route('checkout.index')
                ->withErrors($exception->errors());
        } catch (\Throwable $exception) {
            Log::error('Payment verification exception', [
                'user_id' => $request->user()?->id,
                'reference' => $reference,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            report($exception);

            return redirect()
                ->route('checkout.index')
                ->with('error', 'Unable to verify payment right now. Please contact support if you were charged.');
        }
    }

    public function reverifyPayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
        ]);

        $reference = trim((string) $data['reference']);
        $user = $request->user();
        $allowCrossUserReverify = $user && $user->can(PermissionNames::MANAGE_ADMIN_PAYMENT_RECOVERY);

        try {
            $result = $allowCrossUserReverify
                ? $this->checkoutService->reverifyPayment($reference)
                : $this->checkoutService->verifyPayment($user, $reference);

            return redirect()
                ->route('order.success', ['order' => $result['order']->id])
                ->with('success', $result['message']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        } catch (\Throwable $exception) {
            Log::error('Manual payment re-verification failed', [
                'user_id' => $user?->id,
                'reference' => $reference,
                'error' => $exception->getMessage(),
            ]);

            report($exception);

            return back()->with('error', 'Unable to re-verify payment at the moment.');
        }
    }

    public function success(Request $request): InertiaResponse
    {
        $orderId = $request->integer('order');

        $order = null;
        if ($orderId > 0) {
            $order = Order::query()
                ->whereKey($orderId)
                ->where('user_id', $request->user()->id)
                ->first();
        }

        return Inertia::render('Checkout/Success', [
            'order' => $order ? [
                'id' => (int) $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total_amount' => (float) $order->total_amount,
                'currency' => $order->currency,
                'created_at' => optional($order->created_at)?->toIso8601String(),
            ] : null,
            'cartCount' => $this->cartService->getCartCount((int) $request->user()->id),
            'categories' => $this->productService->listStoreCategories(),
        ]);
    }

    protected function selectionRules(Request $request): array
    {
        return [
            'coupon' => ['nullable', 'string', 'max:64'],
            'address_id' => [
                'nullable',
                'integer',
                Rule::exists('customer_addresses', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
            'shipping_method_id' => ['nullable', 'integer', 'exists:shipping_methods,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'lga_id' => ['nullable', 'integer', 'exists:lgas,id'],
            'pickup_location_id' => ['nullable', 'integer', 'exists:pickup_locations,id'],
            'phone' => ['nullable', 'string', 'max:20'],
            'line1' => ['nullable', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'save_address' => ['nullable', 'boolean'],
        ];
    }

    protected function selectionRouteParams(array $data): array
    {
        $coupon = trim((string) ($data['coupon'] ?? ''));

        return [
            'coupon' => $coupon !== '' ? $coupon : null,
            'address_id' => $data['address_id'] ?? null,
            'shipping_method_id' => $data['shipping_method_id'] ?? null,
            'state_id' => $data['state_id'] ?? null,
            'lga_id' => $data['lga_id'] ?? null,
            'pickup_location_id' => $data['pickup_location_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'line1' => $data['line1'] ?? null,
            'line2' => $data['line2'] ?? null,
            'save_address' => $data['save_address'] ?? null,
        ];
    }
}

