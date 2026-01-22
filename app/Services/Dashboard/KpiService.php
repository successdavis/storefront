<?php

namespace App\Services\Dashboard;

use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;

class KpiService
{
    public function get(array $range): array
    {
        [$from, $to] = $this->resolveDateRange($range);
        $comparison = $this->compareToPreviousPeriod($range);

        // Only real money received
        $payments = Payment::query()
            ->where('type', 'inflow')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to]);

        $totalRevenue = (clone $payments)->sum('amount');

        $orders = Order::query()
            ->whereIn('status', ['paid', 'completed'])
            ->whereBetween('created_at', [$from, $to]);

        $posRevenue = (clone $payments)
            ->whereHasMorph('payable', [Order::class], function ($q) {
                $q->where('channel', 'pos');
            })
            ->sum('amount');

        $onlineRevenue = (clone $payments)
            ->whereHasMorph('payable', [Order::class], function ($q) {
                $q->where('channel', 'online');
            })
            ->sum('amount');

        $posOrders = (clone $orders)->where('channel', 'pos')->count();
        $onlineOrders = (clone $orders)->where('channel', 'online')->count();
        $totalOrders = $posOrders + $onlineOrders;

        $posProgress = $totalRevenue > 0
            ? round(($posRevenue / $totalRevenue) * 100)
            : 0;

        $onlineProgress = $totalRevenue > 0
            ? round(($onlineRevenue / $totalRevenue) * 100)
            : 0;

        return [
            'totalRevenue' => [
                'value' => $this->money($totalRevenue),
                'subtitle' => $comparison['percent'],
                'compare_label' => $comparison['label'],
            ],
            'posSales' => [
                'value' => $this->money($posRevenue),
                'progress' => $posProgress,
            ],
            'onlineSales' => [
                'value' => $this->money($onlineRevenue),
                'progress' => $onlineProgress,
            ],
            'totalOrders' => [
                'value' => $totalOrders,
                'subtitle' => "{$posOrders} POS, {$onlineOrders} Online",
            ],
        ];
    }

    /* ---------------- DATE RANGES ---------------- */

    private function resolveDateRange(array $range): array
    {
        $type = $range['type'] ?? 'today';

        return match ($type) {
            'yesterday' => [
                now()->subDay()->startOfDay(),
                now()->subDay()->endOfDay(),
            ],
            'last_7_days' => [
                now()->subDays(6)->startOfDay(),
                now()->endOfDay(),
            ],
            'this_month' => [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ],
            'last_month' => [
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth(),
            ],
            'last_three_months' => [
                now()->subMonths(3)->startOfDay(),
                now()->endOfDay(),
            ],
            'last_six_months' => [
                now()->subMonths(6)->startOfDay(),
                now()->endOfDay(),
            ],
            'this_year' => [
                now()->startOfYear(),
                now()->endOfYear(),
            ],
            'all_time' => [
                Payment::min('paid_at') ?? now()->startOfDay(),
                now()->endOfDay(),
            ],
            default => [
                now()->startOfDay(),
                now()->endOfDay(),
            ],
        };
    }

    private function resolvePreviousRange(array $range): array
    {
        $type = $range['type'] ?? 'today';

        return match ($type) {
            'today' => [
                now()->subDay()->startOfDay(),
                now()->subDay()->endOfDay(),
            ],
            'yesterday' => [
                now()->subDays(2)->startOfDay(),
                now()->subDays(2)->endOfDay(),
            ],
            'last_7_days' => [
                now()->subDays(13)->startOfDay(),
                now()->subDays(7)->endOfDay(),
            ],
            'this_month' => [
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth(),
            ],
            'last_month' => [
                now()->subMonths(2)->startOfMonth(),
                now()->subMonths(2)->endOfMonth(),
            ],
            'last_three_months' => [
                now()->subMonths(6)->startOfDay(),
                now()->subMonths(3)->endOfDay(),
            ],
            'last_six_months' => [
                now()->subMonths(12)->startOfDay(),
                now()->subMonths(6)->endOfDay(),
            ],
            'this_year' => [
                now()->subYear()->startOfYear(),
                now()->subYear()->endOfYear(),
            ],
            'all_time' => [
                null,
                null,
            ],
            default => [
                now()->subDay()->startOfDay(),
                now()->subDay()->endOfDay(),
            ],
        };
    }

    /* ---------------- COMPARISON ---------------- */

    private function compareToPreviousPeriod(array $range): array
    {
        [$currentFrom, $currentTo] = $this->resolveDateRange($range);
        [$previousFrom, $previousTo] = $this->resolvePreviousRange($range);

        $current = Payment::where('type', 'inflow')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$currentFrom, $currentTo])
            ->sum('amount');

        if (!$previousFrom || !$previousTo) {
            return [
                'percent' => '',
                'label' => 'lifetime',
            ];
        }

        $previous = Payment::where('type', 'inflow')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$previousFrom, $previousTo])
            ->sum('amount');

        if ($previous == 0) {
            return [
                'percent' => '+100%',
                'label' => $this->comparisonLabel($range['type'] ?? 'today'),
            ];
        }

        $diff = (($current - $previous) / $previous) * 100;
        $sign = $diff >= 0 ? '+' : '';

        return [
            'percent' => $sign . round($diff, 1) . '%',
            'label' => $this->comparisonLabel($range['type'] ?? 'today'),
        ];
    }

    private function comparisonLabel(string $type): string
    {
        return match ($type) {
            'today' => 'vs yesterday',
            'yesterday' => 'vs day before',
            'last_7_days' => 'vs previous 7 days',
            'this_month' => 'vs last month',
            'last_month' => 'vs previous month',
            'last_three_months' => 'vs previous 3 months',
            'last_six_months' => 'vs previous 6 months',
            'this_year' => 'vs last year',
            'all_time' => 'lifetime',
            default => '',
        };
    }

    private function money(float $amount): string
    {
        return '₦' . number_format($amount, 2);
    }
}
