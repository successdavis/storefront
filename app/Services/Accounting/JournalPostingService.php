<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JournalPostingService
{
    public function post(array $payload, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($payload, $lines): JournalEntry {
            if (!empty($payload['event_key'])) {
                $existing = JournalEntry::query()
                    ->where('event_key', $payload['event_key'])
                    ->first();

                if ($existing) {
                    return $existing->load('lines.account');
                }
            }

            if (count($lines) < 2) {
                throw ValidationException::withMessages([
                    'lines' => 'A journal entry must contain at least two lines.',
                ]);
            }

            $accountIds = collect($lines)->pluck('account_id')->filter()->unique()->values()->all();
            $accounts = Account::query()
                ->whereIn('id', $accountIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $validatedLines = collect($lines)->values()->map(function (array $line, int $index) use ($accounts) {
                $account = $accounts->get($line['account_id']);

                if (!$account || !$account->is_active) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.account_id" => 'The selected account is inactive or unavailable.',
                    ]);
                }

                $debit = round((float) ($line['debit'] ?? 0), 4);
                $credit = round((float) ($line['credit'] ?? 0), 4);

                if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                    throw ValidationException::withMessages([
                        "lines.{$index}" => 'Each line must contain exactly one non-zero debit or credit value.',
                    ]);
                }

                return [
                    'account_id' => $account->id,
                    'line_number' => $index + 1,
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => $line['description'] ?? null,
                    'entity_type' => $line['entity_type'] ?? null,
                    'entity_id' => $line['entity_id'] ?? null,
                    'meta' => $line['meta'] ?? null,
                ];
            })->all();

            $totalDebit = round(collect($validatedLines)->sum('debit'), 4);
            $totalCredit = round(collect($validatedLines)->sum('credit'), 4);

            if (abs($totalDebit - $totalCredit) > 0.0001) {
                throw ValidationException::withMessages([
                    'lines' => 'Journal entry debits and credits must balance.',
                ]);
            }

            /** @var Model|null $source */
            $source = $payload['source'] ?? null;

            $entry = JournalEntry::query()->create([
                'entry_number' => $payload['entry_number'] ?? $this->generateEntryNumber(),
                'event_key' => $payload['event_key'] ?? null,
                'source_event' => $payload['source_event'] ?? null,
                'entry_date' => $payload['entry_date'] ?? now()->toDateString(),
                'posting_date' => $payload['posting_date'] ?? now()->toDateString(),
                'description' => $payload['description'],
                'source_type' => $source ? $source::class : ($payload['source_type'] ?? null),
                'source_id' => $source?->getKey() ?? ($payload['source_id'] ?? null),
                'status' => $payload['status'] ?? JournalEntry::STATUS_POSTED,
                'currency' => $payload['currency'] ?? config('accounting.currency', 'NGN'),
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'posted_by' => $payload['posted_by'] ?? null,
                'reversal_of_id' => $payload['reversal_of_id'] ?? null,
                'meta' => $payload['meta'] ?? null,
            ]);

            $entry->lines()->createMany($validatedLines);

            return $entry->load('lines.account', 'postedBy');
        });
    }

    public function reverse(JournalEntry $entry, ?int $actorId = null, ?string $description = null, ?string $eventKey = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $actorId, $description, $eventKey): JournalEntry {
            $entry = JournalEntry::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($entry->id);

            if ($entry->status !== JournalEntry::STATUS_POSTED) {
                throw ValidationException::withMessages([
                    'entry' => 'Only posted journal entries can be reversed.',
                ]);
            }

            $reversal = $this->post([
                'event_key' => $eventKey,
                'source_event' => 'reversal',
                'entry_date' => now()->toDateString(),
                'posting_date' => now()->toDateString(),
                'description' => $description ?: "Reversal of {$entry->entry_number}",
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'status' => JournalEntry::STATUS_POSTED,
                'currency' => $entry->currency,
                'posted_by' => $actorId,
                'reversal_of_id' => $entry->id,
                'meta' => [
                    'reversal_of_entry_id' => $entry->id,
                ],
            ], $entry->lines->map(fn ($line) => [
                'account_id' => $line->account_id,
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
                'description' => $line->description,
                'entity_type' => $line->entity_type,
                'entity_id' => $line->entity_id,
                'meta' => $line->meta,
            ])->all());

            $entry->update([
                'status' => JournalEntry::STATUS_REVERSED,
                'reversed_by' => $actorId,
                'meta' => array_merge($entry->meta ?? [], [
                    'reversal_entry_id' => $reversal->id,
                ]),
            ]);

            return $reversal;
        });
    }

    protected function generateEntryNumber(): string
    {
        do {
            $candidate = 'JE-'.now()->format('Ymd').'-'.Str::upper(Str::random(10));
        } while (JournalEntry::query()->where('entry_number', $candidate)->exists());

        return $candidate;
    }
}
