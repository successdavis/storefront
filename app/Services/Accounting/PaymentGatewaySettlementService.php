<?php

namespace App\Services\Accounting;

use App\Models\Accounting\PaymentGatewaySettlement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentGatewaySettlementService
{
    public function __construct(
        protected AccountingService $accountingService,
    ) {}

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return PaymentGatewaySettlement::query()
            ->with([
                'bankAccount:id,code,name',
                'clearingAccount:id,code,name',
                'recorder:id,name',
                'journalEntry:id,entry_number',
            ])
            ->when($search !== '', fn ($query) => $query->where(function ($builder) use ($search) {
                $builder->where('settlement_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('gateway', 'like', "%{$search}%");
            }))
            ->latest('settlement_date')
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();
    }

    public function create(array $data, int $actorId): PaymentGatewaySettlement
    {
        return DB::transaction(function () use ($data, $actorId): PaymentGatewaySettlement {
            return PaymentGatewaySettlement::query()->create([
                'settlement_number' => $this->nextSettlementNumber(),
                'gateway' => strtolower((string) $data['gateway']),
                'settlement_date' => $data['settlement_date'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? config('accounting.currency', 'NGN'),
                'bank_account_id' => $data['bank_account_id'],
                'clearing_account_id' => $data['clearing_account_id'],
                'reference' => $data['reference'] ?? null,
                'status' => PaymentGatewaySettlement::STATUS_POSTED,
                'description' => $data['description'],
                'note' => $data['note'] ?? null,
                'recorded_by' => $actorId,
                'meta' => $data['meta'] ?? null,
            ]);
        });
    }

    public function createAndPost(array $data, int $actorId): PaymentGatewaySettlement
    {
        return DB::transaction(function () use ($data, $actorId): PaymentGatewaySettlement {
            $settlement = $this->create($data, $actorId);
            $entry = $this->accountingService->postGatewaySettlement($settlement, $actorId);

            if (!$settlement->journal_entry_id) {
                $settlement->forceFill(['journal_entry_id' => $entry->id])->save();
            }

            return $settlement->fresh([
                'bankAccount:id,code,name',
                'clearingAccount:id,code,name',
                'journalEntry:id,entry_number',
                'recorder:id,name',
            ]);
        });
    }

    protected function nextSettlementNumber(): string
    {
        do {
            $candidate = 'SET-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (PaymentGatewaySettlement::query()->where('settlement_number', $candidate)->exists());

        return $candidate;
    }
}
