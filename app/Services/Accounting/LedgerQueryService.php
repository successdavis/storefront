<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class LedgerQueryService
{
    public function statement(Account $account, array $filters = []): array
    {
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        $openingRaw = $this->baseScope($account)
            ->whereDate('journal_entries.posting_date', '<', $from)
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit - journal_entry_lines.credit), 0) as balance')
            ->value('balance');

        $running = (float) $openingRaw;

        $movements = $this->movementQuery($account)
            ->whereBetween('journal_entries.posting_date', [$from, $to])
            ->orderBy('journal_entries.posting_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_entry_lines.line_number')
            ->paginate((int) ($filters['per_page'] ?? 50))
            ->through(function (JournalEntryLine $line) use (&$running, $account) {
                $running += (float) $line->debit - (float) $line->credit;

                return [
                    'id' => (int) $line->id,
                    'entry_number' => $line->journalEntry->entry_number,
                    'posting_date' => optional($line->journalEntry->posting_date)?->toDateString(),
                    'description' => $line->description ?: $line->journalEntry->description,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                    'running_balance' => $this->presentBalance($account, $running),
                    'source_type' => $line->journalEntry->source_type,
                    'source_id' => $line->journalEntry->source_id,
                ];
            });

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'subtype' => $account->subtype,
            ],
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
            'opening_balance' => $this->presentBalance($account, (float) $openingRaw),
            'closing_balance' => $this->presentBalance($account, $running),
            'movements' => $movements,
        ];
    }

    protected function baseScope(Account $account): Builder
    {
        return JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entry_lines.account_id', $account->id)
            ->where('journal_entries.status', 'posted');
    }

    protected function movementQuery(Account $account): Builder
    {
        return $this->baseScope($account)
            ->select('journal_entry_lines.*')
            ->with('journalEntry:id,entry_number,posting_date,description,source_type,source_id');
    }

    protected function presentBalance(Account $account, float $signedRaw): array
    {
        $normalDebit = $account->normalBalanceSide() === 'debit';
        $display = $normalDebit ? $signedRaw : -1 * $signedRaw;

        return [
            'amount' => round(abs($display), 4),
            'side' => $display >= 0 ? $account->normalBalanceSide() : ($normalDebit ? 'credit' : 'debit'),
            'signed' => round($display, 4),
        ];
    }
}
