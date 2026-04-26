<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use Illuminate\Support\Collection;

class FinancialStatementService
{
    public function incomeStatement(array $filters = []): array
    {
        $trial = collect(app(TrialBalanceService::class)->report($filters)['rows']);

        $revenue = $this->statementRows($trial->where('type', 'revenue'), false);
        $costOfGoodsSold = $this->statementRows($trial->where('type', 'cost_of_goods_sold'), true);
        $operatingExpenses = $this->statementRows($trial->where('type', 'expense'), true);
        $revenueTotal = round($revenue->sum('signed_amount'), 4);
        $cogsTotal = round($costOfGoodsSold->sum('signed_amount'), 4);
        $operatingExpensesTotal = round($operatingExpenses->sum('signed_amount'), 4);
        $grossProfit = round($revenueTotal - $cogsTotal, 4);
        $netProfit = round($grossProfit - $operatingExpensesTotal, 4);

        return [
            'filters' => [
                'from' => $filters['from'] ?? now()->startOfMonth()->toDateString(),
                'to' => $filters['to'] ?? now()->toDateString(),
            ],
            'revenue' => $revenue,
            'cost_of_goods_sold' => $costOfGoodsSold,
            'operating_expenses' => $operatingExpenses,
            'revenue_total' => $revenueTotal,
            'cost_of_goods_sold_total' => $cogsTotal,
            'gross_profit' => $grossProfit,
            'operating_expenses_total' => $operatingExpensesTotal,
            'net_profit' => $netProfit,
            'gross_margin_percent' => $revenueTotal > 0 ? round(($grossProfit / $revenueTotal) * 100, 2) : 0.0,
            'net_margin_percent' => $revenueTotal > 0 ? round(($netProfit / $revenueTotal) * 100, 2) : 0.0,
        ];
    }

    public function balanceSheet(array $filters = []): array
    {
        $trialReport = app(TrialBalanceService::class)->reportAsOf($filters);
        $trial = collect($trialReport['rows']);
        $asOf = $trialReport['filters']['as_of'] ?? ($filters['as_of'] ?? now()->toDateString());

        $assets = $this->statementRows($trial->where('type', 'asset'), true);
        $liabilities = $this->statementRows($trial->where('type', 'liability'), false);
        $equity = $this->statementRows($trial->where('type', 'equity'), false);
        $unclosedEarnings = $this->unclosedEarnings($trial);

        if (abs($unclosedEarnings) > 0.0001) {
            $equity = $equity
                ->push([
                    'code' => '3210-DERIVED',
                    'name' => 'Accumulated Earnings',
                    'type' => 'equity',
                    'subtype' => 'retained_earnings',
                    'signed_amount' => round($unclosedEarnings, 4),
                    'amount' => round(abs($unclosedEarnings), 4),
                    'is_negative' => $unclosedEarnings < 0,
                    'derived' => true,
                ])
                ->values();
        }

        return [
            'filters' => [
                'as_of' => $asOf,
            ],
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'totals' => [
                'assets' => round($assets->sum('signed_amount'), 4),
                'liabilities' => round($liabilities->sum('signed_amount'), 4),
                'equity' => round($equity->sum('signed_amount'), 4),
            ],
        ];
    }

    protected function statementRows(Collection $rows, bool $preferDebit): Collection
    {
        return $rows->map(function (array $row) use ($preferDebit) {
            $debit = (float) ($row['raw_debit'] ?? $row['debit'] ?? 0);
            $credit = (float) ($row['raw_credit'] ?? $row['credit'] ?? 0);
            $amount = $preferDebit ? ($debit - $credit) : ($credit - $debit);

            return [
                'code' => $row['code'],
                'name' => $row['name'],
                'type' => $row['type'],
                'subtype' => $row['subtype'],
                'signed_amount' => round($amount, 4),
                'amount' => round(abs($amount), 4),
                'is_negative' => $amount < 0,
            ];
        })->filter(fn (array $row) => $row['amount'] > 0)->values();
    }

    protected function unclosedEarnings(Collection $trialRows): float
    {
        $revenue = round((float) $trialRows->where('type', 'revenue')->sum(fn (array $row) => ((float) ($row['raw_credit'] ?? $row['credit'] ?? 0)) - ((float) ($row['raw_debit'] ?? $row['debit'] ?? 0))), 4);
        $costOfGoodsSold = round((float) $trialRows->where('type', 'cost_of_goods_sold')->sum(fn (array $row) => ((float) ($row['raw_debit'] ?? $row['debit'] ?? 0)) - ((float) ($row['raw_credit'] ?? $row['credit'] ?? 0))), 4);
        $expenses = round((float) $trialRows->where('type', 'expense')->sum(fn (array $row) => ((float) ($row['raw_debit'] ?? $row['debit'] ?? 0)) - ((float) ($row['raw_credit'] ?? $row['credit'] ?? 0))), 4);

        return round($revenue - $costOfGoodsSold - $expenses, 4);
    }
}
