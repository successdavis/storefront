<?php

namespace App\Services\Dashboard;

use App\Models\Payment;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalesChartService
{
    public function get(string $range): array
    {
        [$from, $to, $group] = $this->resolveRange($range);

        $rows = Payment::query()
            ->where('payments.payable_type', Order::class)
            ->where('payments.type', 'inflow')
            ->where('payments.status', 'paid')
            ->whereBetween('payments.paid_at', [$from, $to])
            ->join('orders', 'payments.payable_id', '=', 'orders.id')
            ->selectRaw($this->selectClause($group))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $this->formatForECharts($rows, $from, $to, $group);
    }

    /* ---------- RANGE ---------- */

    private function resolveRange(string $range): array
    {
        if (in_array($range, ['today', 'yesterday'])) {
            return [
                Carbon::now()->subDays(6)->startOfDay(),
                Carbon::now(),
                'day',
            ];
        }

        return match ($range) {
            'last_7_days' => [
                Carbon::now()->subDays(6)->startOfDay(),
                Carbon::now(),
                'day',
            ],

            'this_month' => [
                Carbon::now()->startOfMonth(),
                Carbon::now(),            // ✅ STOP AT TODAY
                'day',
            ],

            'last_month' => [
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth(),
                'day',
            ],

            'last_three_months' => [
                Carbon::now()->subMonths(2)->startOfMonth(),
                Carbon::now(),            // ✅ STOP AT TODAY
                'month',
            ],

            'last_six_months' => [
                Carbon::now()->subMonths(5)->startOfMonth(),
                Carbon::now(),            // ✅ STOP AT TODAY
                'month',
            ],

            'this_year' => [
                Carbon::now()->startOfYear(),
                Carbon::now(),            // ✅ STOP AT TODAY
                'month',
            ],

            'all_time' => [
                Payment::min('paid_at') ?? Carbon::now(),
                Carbon::now(),            // ✅ ALWAYS NOW
                'month',
            ],

            default => [
                Carbon::now()->subDays(6)->startOfDay(),
                Carbon::now(),
                'day',
            ],
        };
    }


    /* ---------- SQL ---------- */

    private function selectClause(string $group): string
    {
        if ($group === 'month') {
            return "
                DATE_FORMAT(payments.paid_at, '%Y-%m-01') as period,
                SUM(CASE WHEN orders.channel = 'online' THEN payments.amount ELSE 0 END) as online,
                SUM(CASE WHEN orders.channel = 'pos' THEN payments.amount ELSE 0 END) as in_store
            ";
        }

        return "
            DATE(payments.paid_at) as period,
            SUM(CASE WHEN orders.channel = 'online' THEN payments.amount ELSE 0 END) as online,
            SUM(CASE WHEN orders.channel = 'pos' THEN payments.amount ELSE 0 END) as in_store
        ";
    }

    /* ---------- FORMAT FOR ECHARTS ---------- */

    private function formatForECharts(
        Collection $rows,
        Carbon $from,
        Carbon $to,
        string $group
    ): array {
        $map = $rows->keyBy('period');

        $labels = [];
        $onlineData = [];
        $inStoreData = [];

        $cursor = $from->copy();

        while ($cursor <= $to) {
            $key = $group === 'month'
                ? $cursor->format('Y-m-01')
                : $cursor->toDateString();

            $row = $map->get($key);

            $labels[] = $group === 'month'
                ? $cursor->format('M Y')
                : $cursor->format('d M');

            $onlineData[] = (float) ($row->online ?? 0);
            $inStoreData[] = (float) ($row->in_store ?? 0);

            $group === 'month'
                ? $cursor->addMonth()
                : $cursor->addDay();
        }

        return [
            'xAxis' => $labels,
            'series' => [
                [
                    'name' => 'Online',
                    'type' => 'line',
                    'smooth' => true,
                    'data' => $onlineData,
                ],
                [
                    'name' => 'In-Store',
                    'type' => 'line',
                    'smooth' => true,
                    'data' => $inStoreData,
                ],
            ],
        ];
    }
}
