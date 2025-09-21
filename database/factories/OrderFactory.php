<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $channel   = $this->faker->randomElement(['online', 'pos']);

        $subtotal  = $this->faker->randomFloat(2, 20000, 500000);
        $discount  = $this->faker->randomFloat(2, 0, min(30000, $subtotal * 0.30));
        $shipping  = $this->faker->randomFloat(2, 0, 5000);
        $tax       = round(($subtotal - $discount) * 0.075, 2); // simple 7.5% VAT example

        $orderNo   = sprintf(
            '%s-%s-%s',
            $channel === 'pos' ? 'POS' : 'WEB',
            now()->format('Ymd'),
            Str::upper(Str::ulid())
        );

        return [
            'user_id'        => User::factory(),
            'subtotal'       => $subtotal,
            'shipping_total' => $shipping,
            'tax_total'      => $tax,
            'discount'       => $discount,
            'currency'       => 'NGN',
            'channel'        => $channel,
            'order_number'   => $orderNo,
        ];
    }

    public function online(): self
    {
        return $this->state(function () {
            return [
                'channel'      => 'online',
                'order_number' => 'WEB-' . now()->format('Ymd') . '-' . Str::upper(Str::ulid()),
            ];
        });
    }

    public function pos(): self
    {
        return $this->state(function () {
            return [
                'channel'      => 'pos',
                'order_number' => 'POS-' . now()->format('Ymd') . '-' . Str::upper(Str::ulid()),
            ];
        });
    }

    public function zeroShipping(): self
    {
        return $this->state(fn () => ['shipping_total' => 0.00]);
    }

    public function withTotals(float $subtotal, float $discount = 0.00, ?float $shipping = null): self
    {
        return $this->state(function () use ($subtotal, $discount, $shipping) {
            $ship = $shipping ?? 0.00;
            $tax  = round(($subtotal - $discount) * 0.075, 2);
            return [
                'subtotal'       => $subtotal,
                'discount'       => $discount,
                'shipping_total' => $ship,
                'tax_total'      => $tax,
            ];
        });
    }
}
