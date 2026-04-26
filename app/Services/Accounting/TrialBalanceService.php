<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrialBalanceService
{
    public function report(array $filters = []): array
    {
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        $rows = Account::query()
            ->leftJoin('journal_entry_lines', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->leftJoin('journal_entries', function ($join) use ($from, $to) {
                $join->on('journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                    ->where('journal_entries.status', '=', 'posted')
                    ->whereBetween('journal_entries.posting_date', [$from, $to]);
            })
            ->select(
                'accounts.id',
                'accounts.code',
                'accounts.name',
                'accounts.type',
                'accounts.subtype'
            )
            ->selectRaw('COALESCE(SUM(CASE WHEN journal_entries.id IS NOT NULL THEN journal_entry_lines.debit ELSE 0 END), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(CASE WHEN journal_entries.id IS NOT NULL THEN journal_entry_lines.credit ELSE 0 END), 0) as total_credit')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type', 'accounts.subtype')
            ->orderBy('accounts.code')
            ->get()
            ->map(function ($row) {
                $debit = (float) $row->total_debit;
                $credit = (float) $row->total_credit;
                $net = $debit - $credit;
                $normalDebit = in_array($row->type, ['asset', 'expense', 'cost_of_goods_sold'], true);
                $presentation = $normalDebit ? $net : -1 * $net;

                return [
                    'id' => (int) $row->id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'type' => $row->type,
                    'subtype' => $row->subtype,
                    'debit' => round($presentation >= 0 ? abs($presentation) : 0, 4),
                    'credit' => round($presentation < 0 ? abs($presentation) : 0, 4),
                    'raw_debit' => round($debit, 4),
                    'raw_credit' => round($credit, 4),
                ];
            })
            ->filter(fn (array $row) => $row['debit'] > 0 || $row['credit'] > 0)
            ->values();

        return [
            'filters' => ['from' => $from, 'to' => $to],
            'rows' => $rows,
            'totals' => [
                'debit' => round($rows->sum('debit'), 4),
                'credit' => round($rows->sum('credit'), 4),
            ],
        ];
    }

    public function reportAsOf(array $filters = []): array
    {
        $asOf = $filters['as_of'] ?? $filters['to'] ?? now()->toDateString();

        $rows = Account::query()
            ->leftJoin('journal_entry_lines', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->leftJoin('journal_entries', function ($join) use ($asOf) {
                $join->on('journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                    ->where('journal_entries.status', '=', 'posted')
                    ->whereDate('journal_entries.posting_date', '<=', $asOf);
            })
            ->select(
                'accounts.id',
                'accounts.code',
                'accounts.name',
                'accounts.type',
                'accounts.subtype'
            )
            ->selectRaw('COALESCE(SUM(CASE WHEN journal_entries.id IS NOT NULL THEN journal_entry_lines.debit ELSE 0 END), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(CASE WHEN journal_entries.id IS NOT NULL THEN journal_entry_lines.credit ELSE 0 END), 0) as total_credit')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type', 'accounts.subtype')
            ->orderBy('accounts.code')
            ->get()
            ->map(function ($row) {
                $debit = (float) $row->total_debit;
                $credit = (float) $row->total_credit;
                $net = $debit - $credit;
                $normalDebit = in_array($row->type, ['asset', 'expense', 'cost_of_goods_sold'], true);
                $presentation = $normalDebit ? $net : -1 * $net;

                return [
                    'id' => (int) $row->id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'type' => $row->type,
                    'subtype' => $row->subtype,
                    'debit' => round($presentation >= 0 ? abs($presentation) : 0, 4),
                    'credit' => round($presentation < 0 ? abs($presentation) : 0, 4),
                    'raw_debit' => round($debit, 4),
                    'raw_credit' => round($credit, 4),
                ];
            })
            ->filter(fn (array $row) => $row['debit'] > 0 || $row['credit'] > 0)
            ->values();

        return [
            'filters' => ['as_of' => $asOf],
            'rows' => $rows,
            'totals' => [
                'debit' => round($rows->sum('debit'), 4),
                'credit' => round($rows->sum('credit'), 4),
            ],
        ];
    }
}
