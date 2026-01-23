<?php

namespace App\Services\Dashboard;

use App\Models\Order;
use Carbon\Carbon;

class RecentTransactionService
{
    public function get(int $limit = 10, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->subDays(7)->startOfDay();
        $to   ??= now()->endOfDay();

        $orders = Order::query()
            ->with([
                'user:id,name',
                'sale.posTerminal:id,name',
                'sale.customer:id,name',
                'payments' => fn ($q) =>
                    $q->where('type', 'inflow')->latest()
            ])
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->limit($limit)
            ->get();

        return $orders->map(function (Order $order) {
            $payment = $order->payments->first();

            return [
                'id'       => substr($order->order_number, -8),
                'source'   => $this->resolveSource($order),
                'customer' => $this->resolveCustomer($order),
                'amount'   => (float) ($payment?->amount ?? $order->total_amount),
                'status'   => ucfirst($order->status),
            ];
        })->toArray();
    }

    /* ------------------ Helpers ------------------ */

    protected function resolveSource(Order $order): string
    {
        if ($order->channel === 'pos') {
            return optional($order->sales?->posTerminal)->name ?? 'POS Terminal';
        }

        return 'Online Store';
    }

    protected function resolveCustomer(Order $order): string
    {
        if ($order->channel === 'pos') {
            return optional($order->sale?->customer)->name ?? 'Walk-in';
        }

        return optional($order->user)->name ?? 'Guest';
    }
}
