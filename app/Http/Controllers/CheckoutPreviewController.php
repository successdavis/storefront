<?php

namespace App\Http\Controllers;

use App\Exceptions\ShippingRateNotFoundException;
use App\Models\User;
use App\Services\PricingQuoteService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CheckoutPreviewController extends Controller
{
    public function __construct(
        protected PricingQuoteService $pricingQuoteService,
    ) {}

    public function preview(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|integer|exists:product_variants,id',
            'items.*.quantity'   => 'required|numeric|min:0.0001',
            'shipping' => 'nullable|array',
            'coupon'   => 'nullable|string',
            'channel'  => 'nullable|in:online,pos',
            'customer_id' => 'nullable|integer|exists:users,id',
            'checkout_token' => 'nullable|string|exists:checkout_sessions,token',
        ]);

        $channel = $data['channel'] ?? 'online';
        $user = $this->resolveUserId($data, $channel);

        try {
            $quote = $this->pricingQuoteService->quote([
                'items' => $data['items'],
                'shipping' => $data['shipping'] ?? null,
                'coupon' => $data['coupon'] ?? null,
                'channel' => $channel,
                'user' => $user,
                'tax_total' => 0.0,
            ]);
        } catch (ShippingRateNotFoundException | InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'shipping' => [$exception->getMessage()],
                ],
            ], 422);
        }

        $now = Carbon::now();
        $expiresAt = $now->copy()->addMinutes(30);
        $token = DB::transaction(function () use ($data, $quote, $channel, $user, $now, $expiresAt) {
            $existing = null;
            if (!empty($data['checkout_token'])) {
                $existing = DB::table('checkout_sessions')
                    ->where('token', $data['checkout_token'])
                    ->where('used', false)
                    ->lockForUpdate()
                    ->first();
            }

            if ($existing) {
                DB::table('checkout_sessions')->where('id', $existing->id)->update([
                    'user_id' => $user?->id,
                    'items' => json_encode($quote['items']),
                    'subtotal' => $quote['summary']['subtotal'],
                    'shipping_total' => $quote['summary']['shipping_total'],
                    'discount_amount' => $quote['summary']['discount_amount'],
                    'discount_id' => $quote['summary']['discount_id'],
                    'discount_snapshot' => json_encode($quote['discount_snapshot']),
                    'shipping_snapshot' => json_encode($quote['shipping_snapshot']),
                    'total' => $quote['summary']['total'],
                    'channel' => $channel,
                    'expires_at' => $expiresAt,
                    'updated_at' => $now,
                ]);

                return $existing->token;
            }

            $token = hash_hmac('sha256', Str::uuid()->toString() . now()->timestamp, (string) config('app.key'));
            DB::table('checkout_sessions')->insert([
                'token' => $token,
                'user_id' => $user?->id,
                'items' => json_encode($quote['items']),
                'subtotal' => $quote['summary']['subtotal'],
                'shipping_total' => $quote['summary']['shipping_total'],
                'discount_amount' => $quote['summary']['discount_amount'],
                'discount_id' => $quote['summary']['discount_id'],
                'discount_snapshot' => json_encode($quote['discount_snapshot']),
                'shipping_snapshot' => json_encode($quote['shipping_snapshot']),
                'total' => $quote['summary']['total'],
                'channel' => $channel,
                'used' => false,
                'payment_status' => 'pending',
                'expires_at' => $expiresAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $token;
        });

        return response()->json([
            'subtotal'       => $quote['summary']['subtotal'],
            'shipping_total' => $quote['summary']['shipping_total'],
            'discount'       => $quote['summary']['discount_amount'],
            'discount_label' => $quote['summary']['discount_label'],
            'total'          => $quote['summary']['total'],
            'checkout_token' => $token,
        ]);
    }

    protected function resolveUserId(array $payload, string $channel)
    {
        $user = !empty($payload['customer_id']) ? User::find($payload['customer_id']) : null;

        if ($channel === 'pos' && empty($user)) {
            $walkInEmail = 'walkInCustomer@example.com';
            $walkInUser = User::where('email', $walkInEmail)->first();
            if (!$walkInUser) {
                // Intentionally fail to prompt admin to create walk-in user as requested.
                throw new InvalidArgumentException("Walk-in customer user with email {$walkInEmail} not found. Please create that user or provide user_id.");
            }
            $user = $walkInUser;
        }

        return $user;
    }
}
