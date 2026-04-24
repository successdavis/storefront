<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Expense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExpenseService
{
    public function __construct(
        protected JournalPostingService $journalPostingService,
    ) {}

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Expense::query()
            ->with(['expenseAccount:id,code,name', 'paymentAccount:id,code,name', 'recorder:id,name'])
            ->when($search !== '', fn ($query) => $query->where(function ($builder) use ($search) {
                $builder->where('expense_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            }))
            ->latest('expense_date')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();
    }

    public function create(array $data, int $actorId): Expense
    {
        return DB::transaction(function () use ($data, $actorId): Expense {
            $expense = Expense::query()->create([
                'expense_number' => $this->nextExpenseNumber(),
                'expense_date' => $data['expense_date'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? config('accounting.currency', 'NGN'),
                'expense_account_id' => $data['expense_account_id'],
                'payment_account_id' => $data['payment_account_id'],
                'status' => Expense::STATUS_POSTED,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'],
                'note' => $data['note'] ?? null,
                'recorded_by' => $actorId,
                'meta' => $data['meta'] ?? null,
            ]);

            return $expense;
        });
    }

    protected function nextExpenseNumber(): string
    {
        do {
            $candidate = 'EXP-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (Expense::query()->where('expense_number', $candidate)->exists());

        return $candidate;
    }
}
