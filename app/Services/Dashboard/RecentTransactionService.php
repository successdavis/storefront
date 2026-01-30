<?php

namespace App\Services\Dashboard;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class RecentTransactionService
{
    /**
     * DASHBOARD (DO NOT TOUCH)
     */
    public function get(int $limit = 25, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->subDays(60)->startOfDay();
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
                'date'     => Carbon::parse($order->created_at)->format('Y-M-d'),
            ];
        })->toArray();
    }

    /**
     * FULL TRANSACTION EXPLORER
     */
    public function getForExplorer(array $filters): LengthAwarePaginator
    {
        $query = Order::query()
            ->with([
                'user:id,name',
                'sale.posTerminal:id,name',
                'sale.customer:id,name',
                'payments' => fn ($q) =>
                $q->where('type', 'inflow')->latest()
            ]);

        $this->applyExplorerFilters($query, $filters);
        $this->applyExplorerSorting($query, $filters);

        return $query
            ->paginate(25)
            ->withQueryString();
    }

    /* ================= FILTER LOGIC ================= */

    protected function applyExplorerFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) =>
                        $u->where('name', 'like', "%{$search}%")
                        )
                        ->orWhereHas('sale.customer', fn ($c) =>
                        $c->where('name', 'like', "%{$search}%")
                        );
                });
            })

            ->when($filters['status'] ?? null,
                fn ($q, $v) => $q->where('status', strtolower($v))
            )

            ->when($filters['source'] ?? null, function ($q, $v) {
                if ($v === 'POS') {
                    $q->where('channel', 'pos');
                }

                if ($v === 'Online') {
                    $q->where('channel', 'online');
                }
            })

            ->when($filters['method'] ?? null, function ($q, $v) {
                $q->whereHas('payments', fn ($p) =>
                $p->where('method', strtolower($v))
                );
            })

            ->when($filters['currency'] ?? null,
                fn ($q, $v) => $q->where('currency', $v)
            )

            ->when($filters['from'] ?? null,
                fn ($q, $v) => $q->whereDate('created_at', '>=', $v)
            )

            ->when($filters['to'] ?? null,
                fn ($q, $v) => $q->whereDate('created_at', '<=', $v)
            )

            ->when($filters['min_amount'] ?? null,
                fn ($q, $v) => $q->where('total_amount', '>=', $v)
            )

            ->when($filters['max_amount'] ?? null,
                fn ($q, $v) => $q->where('total_amount', '<=', $v)
            );
    }

    protected function applyExplorerSorting(Builder $query, array $filters): void
    {
        $allowedSorts = ['created_at', 'total_amount', 'status'];

        $sort = in_array($filters['sort'] ?? '', $allowedSorts)
            ? $filters['sort']
            : 'created_at';

        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sort, $dir);
    }

    /* ================= EXISTING HELPERS ================= */

    protected function resolveSource(Order $order): string
    {
        if ($order->channel === 'pos') {
            return optional($order->sale?->posTerminal)->name ?? 'POS Terminal';
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
