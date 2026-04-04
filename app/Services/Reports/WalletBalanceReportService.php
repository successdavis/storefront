<?php

namespace App\Services\Reports;

use App\Models\Loan\LoanAccount;
use App\Models\Loan\LoanBranch;
use App\Models\User;
use App\Support\LoanWalletType;
use Illuminate\Support\Collection;

class WalletBalanceReportService
{
    /**
     * @return array<int, array{id:int,name:string,code:string}>
     */
    public function listBranches(): array
    {
        return LoanBranch::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (LoanBranch $branch): array => [
                'id' => (int) $branch->id,
                'name' => $branch->name,
                'code' => (string) $branch->code,
            ])
            ->values()
            ->all();
    }

    public function report(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $selectedBranch = $this->selectedBranch($normalized);
        $rows = $this->summaryRows($normalized);

        return [
            'summary' => [
                'selected_branch' => $selectedBranch ? [
                    'id' => (int) $selectedBranch->id,
                    'name' => $selectedBranch->name,
                    'code' => (string) $selectedBranch->code,
                ] : null,
                'total_active_accounts' => $rows->sum('active_accounts'),
                'total_balance' => (float) $rows->sum('total_balance'),
            ],
            'rows' => $rows->values()->all(),
        ];
    }

    public function pdfPayload(array $filters, ?User $generatedBy = null): array
    {
        return [
            ...$this->report($filters),
            'generated_at' => now(),
            'generated_by' => $generatedBy?->name,
        ];
    }

    public function categoryExportPayload(string $walletTypeKey, array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $selectedBranch = $this->selectedBranch($normalized);

        return [
            'wallet_type' => [
                'key' => $walletTypeKey,
                'label' => LoanWalletType::label($walletTypeKey),
            ],
            'branch' => $selectedBranch ? [
                'id' => (int) $selectedBranch->id,
                'name' => $selectedBranch->name,
                'code' => (string) $selectedBranch->code,
            ] : null,
            'rows' => $this->categoryAccounts($walletTypeKey, $normalized)->all(),
            'generated_at' => now(),
        ];
    }

    protected function summaryRows(array $filters): Collection
    {
        $walletTypeSql = LoanWalletType::caseExpression('accounts.account_type');

        $aggregates = LoanAccount::query()
            ->from('accounts as accounts')
            ->selectRaw("{$walletTypeSql} as wallet_type_key")
            ->selectRaw('COUNT(*) as active_accounts')
            ->selectRaw('COALESCE(SUM(accounts.amount), 0) as total_balance')
            ->where('accounts.status', 1)
            ->when($filters['branch_id'], fn ($query, $branchId) => $query->where('accounts.branch_id', $branchId))
            ->groupBy('wallet_type_key')
            ->get();

        $indexed = $aggregates->keyBy('wallet_type_key');

        return collect(LoanWalletType::labels())->map(
            fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'active_accounts' => (int) data_get($indexed, "{$key}.active_accounts", 0),
                'total_balance' => (float) data_get($indexed, "{$key}.total_balance", 0),
            ]
        )->values();
    }

    protected function categoryAccounts(string $walletTypeKey, array $filters): Collection
    {
        $walletTypeSql = LoanWalletType::caseExpression('accounts.account_type');

        return LoanAccount::query()
            ->from('accounts as accounts')
            ->leftJoin('users as loan_users', 'loan_users.id', '=', 'accounts.user_id')
            ->leftJoin('branches as branches', 'branches.id', '=', 'accounts.branch_id')
            ->where('accounts.status', 1)
            ->whereRaw("{$walletTypeSql} = ?", [$walletTypeKey])
            ->when($filters['branch_id'], fn ($query, $branchId) => $query->where('accounts.branch_id', $branchId))
            ->orderBy('loan_users.name')
            ->orderBy('accounts.account_number')
            ->get([
                'accounts.id',
                'accounts.account_number',
                'accounts.amount',
                'accounts.locked',
                'accounts.created_at',
                'loan_users.name as customer_name',
                'loan_users.email',
                'loan_users.mobile',
                'branches.name as branch_name',
                'branches.code as branch_code',
            ])
            ->map(fn (LoanAccount $account): array => [
                'id' => (int) $account->id,
                'customer_name' => (string) ($account->customer_name ?? 'Unknown Customer'),
                'email' => $account->email,
                'mobile' => $account->mobile,
                'account_number' => $account->account_number,
                'wallet_type' => LoanWalletType::label($walletTypeKey),
                'branch_name' => $account->branch_name,
                'branch_code' => $account->branch_code,
                'current_balance' => (float) $account->amount,
                'locked' => (bool) $account->locked,
                'created_at' => optional($account->created_at)?->toIso8601String(),
            ])
            ->values();
    }

    protected function selectedBranch(array $filters): ?LoanBranch
    {
        if (!$filters['branch_id']) {
            return null;
        }

        return LoanBranch::query()->find($filters['branch_id']);
    }

    protected function normalizeFilters(array $filters): array
    {
        return [
            'branch_id' => isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
        ];
    }
}
