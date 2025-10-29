<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Discount;
use App\Models\OrderDiscount;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiscountService
{
    public function __construct(
        protected float $moneyTolerance = 0.01
    ) {}

    /**
     * PREVIEW: Compute discount amount without mutating DB.
     *
     * @param User|null   $user
     * @param array       $items   Each: ['variant_id'=>int,'quantity'=>float,'unit_price'=>float,'product_id'=>int|null,'category_ids'=>array<int>]
     * @param float       $subtotal
     * @param float       $shippingTotal
     * @param float       $taxTotal
     * @param string      $channel  'online' | 'pos'
     * @param string|null $couponCode
     * @return array{discount_id:int|null, code:string|null, label:string|null, amount:float}
     * @throws ValidationException
     */
    public function previewQuote(
        ?User $user,
        array $items,
        float $subtotal,
        float $shippingTotal = 0.0,
        float $taxTotal = 0.0,
        string $channel = 'online',
        ?string $couponCode = null
    ): array {
        $now = Carbon::now();

        // 1) Candidate discounts
        $candidates = Discount::query()
            ->where('is_active', true)
            ->when($couponCode, fn($q) => $q->where('code', $couponCode),
                fn($q) => $q->whereNull('code'))
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->get();

        if ($candidates->isEmpty() && $couponCode) {
            throw ValidationException::withMessages(['coupon' => 'Invalid or expired coupon.']);
        }

        // 2) Evaluate best discount (Amazon-like: only one)
        $best = ['discount_id' => null, 'code' => $couponCode, 'label' => null, 'amount' => 0.0];

        foreach ($candidates as $discount) {
            if (!$this->isApplicable($discount, $user, $subtotal, $shippingTotal, $channel, $items)) {
                continue;
            }

            $amount = $this->calculateAmount($discount, $items, $subtotal, $shippingTotal, $taxTotal, $channel);
            if ($amount > $best['amount']) {
                $best = [
                    'discount_id' => $discount->id,
                    'code'        => $discount->code,
                    'label'       => $discount->name,
                    'amount'      => round($amount, 2),
                ];
            }
        }

        return $best;
    }

    /**
     * COMMIT: Persist discount usage for an order.
     * Safe for retries, idempotent.
     *
     * @return array{applied:bool, amount:float, discount_id:int|null}
     */
    public function commitForOrder(Order $order, ?string $couponCode = null): array
    {
        $user = $order->user;
        $items = $this->mapOrderItems($order->items);
        $subtotal = (float) $order->subtotal;
        $shippingTotal = (float) $order->shipping_total;
        $taxTotal = (float) $order->tax_total;
        $channel = $order->channel ?? 'online';

        // Recompute best discount
        $quote = $this->previewQuote($user, $items, $subtotal, $shippingTotal, $taxTotal, $channel, $couponCode);

        if (!$quote['discount_id'] || $quote['amount'] <= 0) {
            return ['applied' => false, 'amount' => 0.0, 'discount_id' => null];
        }

        return DB::transaction(function () use ($order, $user, $quote, $subtotal, $shippingTotal, $taxTotal) {

            // Idempotency: if already committed, return
            $existing = OrderDiscount::where('order_id', $order->id)->first();
            if ($existing) {
                return ['applied' => true, 'amount' => (float) $existing->discount_amount, 'discount_id' => $existing->discount_id];
            }

            // Persist
            $row = OrderDiscount::create([
                'order_id'        => $order->id,
                'discount_id'     => $quote['discount_id'],
                'discount_amount' => $quote['amount'],
            ]);

            // Update order
            $order->discount = $quote['amount'];
            $order->total_amount = max(
                round($subtotal + $shippingTotal + $taxTotal - $quote['amount'], 2),
                0
            );
            $order->save();

            // Track per-user usage
            if ($user) {
                DB::table('discount_user')->updateOrInsert(
                    ['discount_id' => $quote['discount_id'], 'user_id' => $user->id],
                    ['times_used' => DB::raw('COALESCE(times_used,0) + 1'), 'updated_at' => now(), 'created_at' => now()]
                );
            }

            return ['applied' => true, 'amount' => (float) $row->discount_amount, 'discount_id' => $row->discount_id];
        }, 3);
    }

    /**
     * Roll back a discount if an order is cancelled/refunded.
     */
    public function revokeForOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $row = OrderDiscount::where('order_id', $order->id)->lockForUpdate()->first();
            if (!$row) return;

            $discountId = $row->discount_id;
            $userId = $order->user_id;

            $row->delete();

            if ($discountId && $userId) {
                $pivot = DB::table('discount_user')
                    ->where('discount_id', $discountId)
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if ($pivot && $pivot->times_used > 0) {
                    DB::table('discount_user')
                        ->where('discount_id', $discountId)
                        ->where('user_id', $userId)
                        ->update(['times_used' => $pivot->times_used - 1, 'updated_at' => now()]);
                }
            }

            $order->discount = 0.0;
            $order->total_amount = round($order->subtotal + $order->shipping_total + $order->tax_total, 2);
            $order->save();
        });
    }

    /**
     * Rules: min order, usage limits, customer scope, free shipping only if shipping > 0, etc.
     */
    protected function isApplicable(Discount $discount, ?User $user, float $subtotal, float $shippingTotal, string $channel, array $items): bool
    {
        if ($discount->min_order_amount && $subtotal < $discount->min_order_amount) {
            return false;
        }

        if ($discount->usage_limit && $discount->orderDiscounts()->count() >= $discount->usage_limit) {
            return false;
        }

        if ($user && $discount->usage_limit_per_user) {
            $timesUsed = (int) ($user->discounts()->where('discount_id', $discount->id)->first()?->pivot?->times_used ?? 0);
            if ($timesUsed >= $discount->usage_limit_per_user) {
                return false;
            }
        }

        if ($discount->customer_scope === 'new_customers' && $user && $user->orders()->exists()) {
            return false;
        }

        // free shipping discount should only apply if shipping exists
        if ($discount->type === 'free_shipping' && $shippingTotal <= 0) {
            return false;
        }

        // Variant / category restrictions
        if ($discount->variants()->exists()) {
            $eligibleVariantIds = collect($items)->pluck('variant_id')->intersect($discount->variants->pluck('id'));
            if ($eligibleVariantIds->isEmpty()) {
                return false;
            }
        }

        if ($discount->categories()->exists()) {
            $allCategoryIds = collect($items)->flatMap(fn($i) => $i['category_ids'])->unique();
            $eligibleCategoryIds = $allCategoryIds->intersect($discount->categories->pluck('id'));
            if ($eligibleCategoryIds->isEmpty()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compute discount amount.
     */
    protected function calculateAmount(
        Discount $discount,
        array $items,
        float $subtotal,
        float $shippingTotal,
        float $taxTotal,
        string $channel = 'online'
    ): float {
        $base = $subtotal;

        // Adjust base if discount is restricted to variants or categories
        if ($discount->variants()->exists()) {
            $eligibleVariantIds = $discount->variants->pluck('id')->all();
            $base = collect($items)->whereIn('variant_id', $eligibleVariantIds)
                ->sum(fn($i) => $i['unit_price'] * $i['quantity']);
        }

        if ($discount->categories()->exists()) {
            $eligibleCategoryIds = $discount->categories->pluck('id')->all();
            $base = collect($items)->filter(function ($i) use ($eligibleCategoryIds) {
                return !empty(array_intersect($i['category_ids'], $eligibleCategoryIds));
            })->sum(fn($i) => $i['unit_price'] * $i['quantity']);
        }

        return match ($discount->type) {
            'percentage'   => round($base * ((float) $discount->value / 100.0), 2),
            'fixed_amount' => min(round((float) $discount->value, 2), round($base, 2)),
            'free_shipping'=> $shippingTotal > 0 ? round($shippingTotal, 2) : 0.0,
            default        => 0.0,
        };
    }

    /**
     * Map Order->items into array for calculations.
     */
    protected function mapOrderItems(Collection $orderItems): array
    {
        return $orderItems->map(function (OrderItem $i) {
            return [
                'variant_id'   => (int) $i->variant_id,
                'quantity'     => (float) $i->quantity,
                'unit_price'   => (float) $i->price,
                'product_id'   => optional($i->variant->product)->id ?? null,
                'category_ids' => optional($i->variant->product)?->categories?->pluck('id')->all() ?? [],
            ];
        })->all();
    }
}
