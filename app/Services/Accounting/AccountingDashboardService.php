<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingDashboardService
{
    public function report(array $filters = []): array
    {
        $from = Carbon::parse($filters['from'] ?? now()->startOfYear()->toDateString())->startOfDay();
        $to = Carbon::parse($filters['to'] ?? now()->toDateString())->endOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $expenseFilters = $this->resolveExpenseFilters($filters, $from, $to);
        $profitLossFilters = $this->resolveProfitLossFilters($filters, $from, $to);

        $periods = $this->monthlyPeriods($from, $to);
        $revenueByMonth = $this->monthlyRevenueTotals($from, $to);
        $cogsByMonth = $this->monthlyCostOfGoodsSoldTotals($from, $to);
        $operatingExpenseByMonth = $this->monthlyOperatingExpenseTotals($from, $to);
        $salesByMonth = $this->monthlySalesTotals($from, $to);
        $cashFlowByMonth = $this->monthlyCashFlowTotals($from, $to);
        $bankBalances = $this->assetBalancesBySubtype(['bank'], $to);
        $cashBalances = $this->assetBalancesBySubtype(['cash'], $to);
        $expenseBreakdown = $this->expenseBreakdown($expenseFilters['from'], $expenseFilters['to']);
        $profitLossSummary = $this->profitLossSummary($profitLossFilters['from'], $profitLossFilters['to']);

        $labels = $periods->values()->all();
        $revenueSeries = $this->seriesFromMap($periods->keys(), $revenueByMonth);
        $cogsSeries = $this->seriesFromMap($periods->keys(), $cogsByMonth);
        $operatingExpenseSeries = $this->seriesFromMap($periods->keys(), $operatingExpenseByMonth);
        $salesSeries = $this->seriesFromMap($periods->keys(), $salesByMonth);
        $cashInflowSeries = $this->seriesFromNestedMap($periods->keys(), $cashFlowByMonth, 'inflow');
        $cashOutflowSeries = $this->seriesFromNestedMap($periods->keys(), $cashFlowByMonth, 'outflow');
        $cashNetSeries = $this->seriesFromNestedMap($periods->keys(), $cashFlowByMonth, 'net');
        $grossProfitSeries = collect($revenueSeries)
            ->map(fn ($revenue, $index) => round($revenue - ($cogsSeries[$index] ?? 0), 2))
            ->values()
            ->all();
        $profitSeries = collect($grossProfitSeries)
            ->map(fn ($grossProfit, $index) => round($grossProfit - ($operatingExpenseSeries[$index] ?? 0), 2))
            ->values()
            ->all();

        $bankTotal = round($bankBalances->sum('balance'), 2);
        $cashTotal = round($cashBalances->sum('balance'), 2);
        $revenueTotal = round(array_sum($revenueSeries), 2);
        $cogsTotal = round(array_sum($cogsSeries), 2);
        $grossProfitTotal = round(array_sum($grossProfitSeries), 2);
        $operatingExpenseTotal = round(array_sum($operatingExpenseSeries), 2);
        $profitTotal = round(array_sum($profitSeries), 2);

        $liquidityRows = $bankBalances
            ->concat($cashBalances)
            ->sortBy('code')
            ->values();

        return [
            'filters' => [
                'period' => $filters['period'] ?? 'this_year',
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'balance_as_of' => $to->toDateString(),
            ],
            'date_presets' => [
                ['value' => 'this_month', 'label' => 'This Month'],
                ['value' => 'last_month', 'label' => 'Last Month'],
                ['value' => 'this_year', 'label' => 'This Year'],
                ['value' => 'last_year', 'label' => 'Last Year'],
                ['value' => 'all_time', 'label' => 'All Time'],
                ['value' => 'custom', 'label' => 'Custom Range'],
            ],
            'summary_cards' => [
                ['key' => 'revenue_total', 'label' => 'Revenue', 'value' => $revenueTotal],
                ['key' => 'cogs_total', 'label' => 'Cost of Goods Sold', 'value' => $cogsTotal],
                ['key' => 'gross_profit_total', 'label' => 'Gross Profit', 'value' => $grossProfitTotal],
                ['key' => 'operating_expense_total', 'label' => 'Operating Expenses', 'value' => $operatingExpenseTotal],
                ['key' => 'profit_total', 'label' => 'Net Profit', 'value' => $profitTotal],
                ['key' => 'bank_balance', 'label' => 'Bank Balance', 'value' => $bankTotal],
                ['key' => 'cash_balance', 'label' => 'Cash Balance', 'value' => $cashTotal],
            ],
            'cash_flow_chart' => [
                'labels' => $labels,
                'inflow' => $cashInflowSeries,
                'outflow' => $cashOutflowSeries,
                'net' => $cashNetSeries,
            ],
            'expense_chart' => [
                'filters' => [
                    'period' => $expenseFilters['period'],
                    'from' => $expenseFilters['from']->toDateString(),
                    'to' => $expenseFilters['to']->toDateString(),
                ],
                'period_options' => [
                    ['value' => 'selected_range', 'label' => 'Selected range'],
                    ['value' => 'last_3_months', 'label' => 'Last 3 months'],
                    ['value' => 'last_6_months', 'label' => 'Last 6 months'],
                    ['value' => 'last_12_months', 'label' => 'Last 12 months'],
                    ['value' => 'this_year', 'label' => 'This year'],
                    ['value' => 'all_time', 'label' => 'All time'],
                ],
                'total' => round($expenseBreakdown->sum('amount'), 2),
                'subtitle' => 'Operating expenses',
                'segments' => $expenseBreakdown->values()->all(),
            ],
            'profit_loss_chart' => [
                'filters' => [
                    'period' => $profitLossFilters['period'],
                    'from' => $profitLossFilters['from']->toDateString(),
                    'to' => $profitLossFilters['to']->toDateString(),
                ],
                'period_options' => [
                    ['value' => 'selected_range', 'label' => 'Selected range'],
                    ['value' => 'this_month', 'label' => 'This month'],
                    ['value' => 'last_month', 'label' => 'Last month'],
                    ['value' => 'this_year', 'label' => 'This year'],
                    ['value' => 'last_year', 'label' => 'Last year'],
                    ['value' => 'all_time', 'label' => 'All time'],
                ],
                'period_label' => $profitLossSummary['period_label'],
                'net_profit' => $profitLossSummary['net_profit'],
                'is_profit' => $profitLossSummary['net_profit'] >= 0,
                'rows' => $profitLossSummary['rows'],
            ],
            'sales_profit_chart' => [
                'labels' => $labels,
                'sales' => $salesSeries,
                'profit' => $profitSeries,
            ],
            'liquidity_chart' => [
                'labels' => $liquidityRows->map(fn (array $row) => $row['name'])->all(),
                'balances' => $liquidityRows->map(fn (array $row) => $row['balance'])->all(),
            ],
            'bank_balances' => $bankBalances->all(),
            'cash_balances' => $cashBalances->all(),
        ];
    }

    protected function resolveExpenseFilters(array $filters, Carbon $fallbackFrom, Carbon $fallbackTo): array
    {
        $period = $filters['expense_period'] ?? 'last_6_months';

        [$from, $to] = match ($period) {
            'last_3_months' => [
                now()->subMonthsNoOverflow(2)->startOfMonth(),
                now()->endOfDay(),
            ],
            'last_12_months' => [
                now()->subMonthsNoOverflow(11)->startOfMonth(),
                now()->endOfDay(),
            ],
            'this_year' => [
                now()->startOfYear(),
                now()->endOfDay(),
            ],
            'all_time' => $this->resolveAllTimePeriod(),
            'selected_range' => [$fallbackFrom->copy(), $fallbackTo->copy()],
            default => [
                now()->subMonthsNoOverflow(5)->startOfMonth(),
                now()->endOfDay(),
            ],
        };

        return [
            'period' => $period,
            'from' => $from->copy()->startOfDay(),
            'to' => $to->copy()->endOfDay(),
        ];
    }

    protected function resolveProfitLossFilters(array $filters, Carbon $fallbackFrom, Carbon $fallbackTo): array
    {
        $period = $filters['profit_loss_period'] ?? 'selected_range';

        [$from, $to] = match ($period) {
            'this_month' => [
                now()->startOfMonth(),
                now()->endOfDay(),
            ],
            'last_month' => [
                now()->subMonthNoOverflow()->startOfMonth(),
                now()->subMonthNoOverflow()->endOfMonth(),
            ],
            'this_year' => [
                now()->startOfYear(),
                now()->endOfDay(),
            ],
            'last_year' => [
                now()->subYear()->startOfYear(),
                now()->subYear()->endOfYear(),
            ],
            'all_time' => $this->resolveAllTimePeriod(),
            'selected_range' => [$fallbackFrom->copy(), $fallbackTo->copy()],
            default => [$fallbackFrom->copy(), $fallbackTo->copy()],
        };

        return [
            'period' => $period,
            'from' => $from->copy()->startOfDay(),
            'to' => $to->copy()->endOfDay(),
        ];
    }

    protected function resolveAllTimePeriod(): array
    {
        $firstPostingDate = DB::table('journal_entries')
            ->where('status', 'posted')
            ->min('posting_date');

        $lastPostingDate = DB::table('journal_entries')
            ->where('status', 'posted')
            ->max('posting_date');

        if ($firstPostingDate === null || $lastPostingDate === null) {
            return [now()->startOfMonth(), now()];
        }

        return [
            Carbon::parse($firstPostingDate)->startOfDay(),
            Carbon::parse($lastPostingDate)->endOfDay(),
        ];
    }

    protected function expenseBreakdown(Carbon $from, Carbon $to): Collection
    {
        $rows = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.posting_date', [$from->toDateString(), $to->toDateString()])
            ->where('accounts.type', 'expense')
            ->select('accounts.id', 'accounts.name')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit - journal_entry_lines.credit), 0) as amount')
            ->groupBy('accounts.id', 'accounts.name')
            ->havingRaw('COALESCE(SUM(journal_entry_lines.debit - journal_entry_lines.credit), 0) > 0')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'amount' => round((float) $row->amount, 2),
            ])
            ->values();

        return $this->collapseMinorExpenseCategories($rows);
    }

    protected function collapseMinorExpenseCategories(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        $total = (float) $rows->sum('amount');

        if ($total <= 0) {
            return collect();
        }

        $threshold = round($total * 0.05, 2);
        $primary = collect();
        $othersTotal = 0.0;

        foreach ($rows as $index => $row) {
            $isMinor = $row['amount'] < $threshold;
            $hasReachedPrimaryLimit = $primary->count() >= 4;

            if (($isMinor || $hasReachedPrimaryLimit) && $rows->count() > 4) {
                $othersTotal += $row['amount'];
                continue;
            }

            $primary->push($row);
        }

        if ($othersTotal > 0) {
            $primary->push([
                'id' => 0,
                'name' => 'Others',
                'amount' => round($othersTotal, 2),
            ]);
        }

        return $primary->values();
    }

    protected function profitLossSummary(Carbon $from, Carbon $to): array
    {
        $current = $this->profitLossTotals($from, $to);
        ['from' => $previousFrom, 'to' => $previousTo] = $this->previousComparisonWindow($from, $to);
        $previous = $this->profitLossTotals($previousFrom, $previousTo);

        $rows = collect([
            [
                'key' => 'revenue',
                'label' => 'Revenue',
                'amount' => $current['revenue'],
                'change_percent' => $this->percentageChange($current['revenue'], $previous['revenue']),
            ],
            [
                'key' => 'cost_of_goods_sold',
                'label' => 'COGS',
                'amount' => $current['cost_of_goods_sold'],
                'change_percent' => $this->percentageChange($current['cost_of_goods_sold'], $previous['cost_of_goods_sold']),
            ],
            [
                'key' => 'operating_expenses',
                'label' => 'Expenses',
                'amount' => $current['operating_expenses'],
                'change_percent' => $this->percentageChange($current['operating_expenses'], $previous['operating_expenses']),
            ],
        ])->filter(function (array $row) {
            return $row['key'] !== 'cost_of_goods_sold' || $row['amount'] > 0;
        })->values();

        return [
            'period_label' => $this->formatPeriodLabel($from, $to),
            'net_profit' => $current['net_profit'],
            'rows' => $rows->all(),
        ];
    }

    protected function profitLossTotals(Carbon $from, Carbon $to): array
    {
        $baseQuery = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.posting_date', [$from->toDateString(), $to->toDateString()]);

        $revenue = (clone $baseQuery)
            ->where('accounts.type', 'revenue')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.credit - journal_entry_lines.debit), 0) as amount')
            ->value('amount');

        $costOfGoodsSold = (clone $baseQuery)
            ->where('accounts.type', 'cost_of_goods_sold')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit - journal_entry_lines.credit), 0) as amount')
            ->value('amount');

        $operatingExpenses = (clone $baseQuery)
            ->where('accounts.type', 'expense')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit - journal_entry_lines.credit), 0) as amount')
            ->value('amount');

        $revenue = round((float) $revenue, 2);
        $costOfGoodsSold = round((float) $costOfGoodsSold, 2);
        $operatingExpenses = round((float) $operatingExpenses, 2);

        return [
            'revenue' => $revenue,
            'cost_of_goods_sold' => $costOfGoodsSold,
            'operating_expenses' => $operatingExpenses,
            'net_profit' => round($revenue - $costOfGoodsSold - $operatingExpenses, 2),
        ];
    }

    protected function previousComparisonWindow(Carbon $from, Carbon $to): array
    {
        $days = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->endOfDay()) + 1);
        $previousTo = $from->copy()->subDay()->endOfDay();
        $previousFrom = $previousTo->copy()->subDays($days - 1)->startOfDay();

        return [
            'from' => $previousFrom,
            'to' => $previousTo,
        ];
    }

    protected function percentageChange(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.0001) {
            return abs($current) < 0.0001 ? 0.0 : null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    protected function formatPeriodLabel(Carbon $from, Carbon $to): string
    {
        if ($from->isSameDay($to)) {
            return $to->format('j M Y');
        }

        if ($from->isSameMonth($to)) {
            return $to->format('F Y');
        }

        if ($from->format('Y') === $to->format('Y')) {
            return sprintf('%s - %s %s', $from->format('j M'), $to->format('j M'), $to->format('Y'));
        }

        return sprintf('%s - %s', $from->format('j M Y'), $to->format('j M Y'));
    }

    protected function monthlyPeriods(Carbon $from, Carbon $to): Collection
    {
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->startOfMonth();
        $periods = collect();

        while ($cursor->lessThanOrEqualTo($end)) {
            $periods->put($cursor->format('Y-m'), $cursor->format('M Y'));
            $cursor->addMonth();
        }

        return $periods;
    }

    protected function monthlyRevenueTotals(Carbon $from, Carbon $to): Collection
    {
        return $this->monthlyAmountQuery($from, $to)
            ->where('accounts.type', 'revenue')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.credit - journal_entry_lines.debit), 0) as amount')
            ->groupBy('period_key')
            ->pluck('amount', 'period_key')
            ->map(fn ($amount) => round((float) $amount, 2));
    }

    protected function monthlyCostOfGoodsSoldTotals(Carbon $from, Carbon $to): Collection
    {
        return $this->monthlyAmountQuery($from, $to)
            ->where('accounts.type', 'cost_of_goods_sold')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit - journal_entry_lines.credit), 0) as amount')
            ->groupBy('period_key')
            ->pluck('amount', 'period_key')
            ->map(fn ($amount) => round((float) $amount, 2));
    }

    protected function monthlyOperatingExpenseTotals(Carbon $from, Carbon $to): Collection
    {
        return $this->monthlyAmountQuery($from, $to)
            ->where('accounts.type', 'expense')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit - journal_entry_lines.credit), 0) as amount')
            ->groupBy('period_key')
            ->pluck('amount', 'period_key')
            ->map(fn ($amount) => round((float) $amount, 2));
    }

    protected function monthlySalesTotals(Carbon $from, Carbon $to): Collection
    {
        return $this->monthlyAmountQuery($from, $to)
            ->where('accounts.type', 'revenue')
            ->whereIn('accounts.subtype', ['sales', 'shipping_income', 'service_fee', 'contra_revenue'])
            ->selectRaw('COALESCE(SUM(journal_entry_lines.credit - journal_entry_lines.debit), 0) as amount')
            ->groupBy('period_key')
            ->pluck('amount', 'period_key')
            ->map(fn ($amount) => round((float) $amount, 2));
    }

    protected function monthlyCashFlowTotals(Carbon $from, Carbon $to): Collection
    {
        return $this->monthlyAmountQuery($from, $to)
            ->where('accounts.type', 'asset')
            ->whereIn('accounts.subtype', ['cash', 'bank'])
            ->where(function ($query) {
                $query->whereNull('journal_entries.source_event')
                    ->orWhereNotIn('journal_entries.source_event', [
                        'cash_bank_transfer_posted',
                        'opening_balance_posted',
                    ]);
            })
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) as inflow')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.credit), 0) as outflow')
            ->groupBy('period_key')
            ->get()
            ->mapWithKeys(function ($row) {
                $inflow = round((float) $row->inflow, 2);
                $outflow = round((float) $row->outflow, 2);

                return [
                    $row->period_key => [
                        'inflow' => $inflow,
                        'outflow' => $outflow,
                        'net' => round($inflow - $outflow, 2),
                    ],
                ];
            });
    }

    protected function assetBalancesBySubtype(array $subtypes, Carbon $asOf): Collection
    {
        return Account::query()
            ->leftJoin('journal_entry_lines', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->leftJoin('journal_entries', function ($join) use ($asOf) {
                $join->on('journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                    ->where('journal_entries.status', '=', 'posted')
                    ->whereDate('journal_entries.posting_date', '<=', $asOf->toDateString());
            })
            ->where('accounts.type', 'asset')
            ->whereIn('accounts.subtype', $subtypes)
            ->where('accounts.is_active', true)
            ->select('accounts.id', 'accounts.code', 'accounts.name', 'accounts.subtype')
            ->selectRaw('COALESCE(SUM(CASE WHEN journal_entries.id IS NOT NULL THEN journal_entry_lines.debit ELSE 0 END), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(CASE WHEN journal_entries.id IS NOT NULL THEN journal_entry_lines.credit ELSE 0 END), 0) as total_credit')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.subtype')
            ->orderBy('accounts.code')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'subtype' => $row->subtype,
                'balance' => round((float) $row->total_debit - (float) $row->total_credit, 2),
            ])
            ->values();
    }

    protected function monthlyAmountQuery(Carbon $from, Carbon $to)
    {
        $periodExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', journal_entries.posting_date)",
            'pgsql' => "TO_CHAR(journal_entries.posting_date, 'YYYY-MM')",
            default => "DATE_FORMAT(journal_entries.posting_date, '%Y-%m')",
        };

        return DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.posting_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("{$periodExpression} as period_key");
    }

    protected function seriesFromMap(Collection $periodKeys, Collection $values): array
    {
        return $periodKeys
            ->map(fn ($key) => round((float) ($values->get($key, 0)), 2))
            ->values()
            ->all();
    }

    protected function seriesFromNestedMap(Collection $periodKeys, Collection $values, string $key): array
    {
        return $periodKeys
            ->map(fn ($period) => round((float) data_get($values->get($period), $key, 0), 2))
            ->values()
            ->all();
    }
}
