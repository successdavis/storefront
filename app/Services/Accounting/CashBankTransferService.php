<?php

namespace App\Services\Accounting;

use App\Models\Accounting\CashBankTransfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CashBankTransferService
{
    public function __construct(
        protected AccountingService $accountingService,
    ) {}

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return CashBankTransfer::query()
            ->with([
                'cashAccount:id,code,name',
                'bankAccount:id,code,name',
                'recorder:id,name',
                'journalEntry:id,entry_number',
            ])
            ->when($search !== '', fn ($query) => $query->where(function ($builder) use ($search) {
                $builder->where('transfer_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->latest('transfer_date')
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();
    }

    public function create(array $data, int $actorId): CashBankTransfer
    {
        return DB::transaction(function () use ($data, $actorId): CashBankTransfer {
            return CashBankTransfer::query()->create([
                'transfer_number' => $this->nextTransferNumber(),
                'transfer_date' => $data['transfer_date'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? config('accounting.currency', 'NGN'),
                'cash_account_id' => $data['cash_account_id'],
                'bank_account_id' => $data['bank_account_id'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'],
                'note' => $data['note'] ?? null,
                'status' => CashBankTransfer::STATUS_POSTED,
                'recorded_by' => $actorId,
                'meta' => $data['meta'] ?? null,
            ]);
        });
    }

    public function createAndPost(array $data, int $actorId): CashBankTransfer
    {
        return DB::transaction(function () use ($data, $actorId): CashBankTransfer {
            $transfer = $this->create($data, $actorId);
            $entry = $this->accountingService->postCashBankTransfer($transfer, $actorId);

            if (!$transfer->journal_entry_id) {
                $transfer->forceFill(['journal_entry_id' => $entry->id])->save();
            }

            return $transfer->fresh([
                'cashAccount:id,code,name',
                'bankAccount:id,code,name',
                'journalEntry:id,entry_number',
                'recorder:id,name',
            ]);
        });
    }

    protected function nextTransferNumber(): string
    {
        do {
            $candidate = 'CBT-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (CashBankTransfer::query()->where('transfer_number', $candidate)->exists());

        return $candidate;
    }
}
