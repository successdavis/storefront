<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $type = trim((string) ($filters['type'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        return Account::query()
            ->with('parent:id,name,code')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when(in_array($type, ['asset', 'liability', 'equity', 'revenue', 'cost_of_goods_sold', 'expense'], true), fn ($query) => $query->where('type', $type))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('code')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function hierarchyOptions(): array
    {
        return Account::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'parent_id', 'classification'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'label' => "{$account->code} · {$account->name}",
                'parent_id' => $account->parent_id,
                'is_header' => $account->isHeader(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function manualPostingAccounts(): array
    {
        return Account::query()
            ->where('is_active', true)
            ->where('allows_manual_entries', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'subtype'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'subtype' => $account->subtype,
                'label' => "{$account->code} · {$account->name}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function bankAccountOptions(): array
    {
        return Account::query()
            ->where('is_active', true)
            ->where('type', 'asset')
            ->where('subtype', 'bank')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'subtype'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'subtype' => $account->subtype,
                'label' => "{$account->code} · {$account->name}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cashAccountOptions(): array
    {
        return Account::query()
            ->where('is_active', true)
            ->where('type', 'asset')
            ->where('subtype', 'cash')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'subtype'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'subtype' => $account->subtype,
                'label' => "{$account->code} Â· {$account->name}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function gatewayClearingOptions(): array
    {
        return Account::query()
            ->where('is_active', true)
            ->where('type', 'asset')
            ->where('subtype', 'clearing')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'subtype'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'subtype' => $account->subtype,
                'label' => "{$account->code} · {$account->name}",
            ])
            ->all();
    }

    public function create(array $data): Account
    {
        $this->guardParentLoop(null, $data['parent_id'] ?? null);

        return Account::query()->create($data);
    }

    public function update(Account $account, array $data): Account
    {
        $this->guardParentLoop($account, $data['parent_id'] ?? $account->parent_id);

        if ($account->is_system) {
            unset($data['code'], $data['slug'], $data['type'], $data['subtype'], $data['classification']);
        }

        $account->fill($data)->save();

        return $account->fresh('parent');
    }

    public function toggleActive(Account $account): Account
    {
        if ($account->is_system && $account->isHeader()) {
            throw ValidationException::withMessages([
                'account' => 'System header accounts cannot be deactivated.',
            ]);
        }

        $account->forceFill(['is_active' => !$account->is_active])->save();

        return $account->fresh();
    }

    protected function guardParentLoop(?Account $account, ?int $parentId): void
    {
        if (!$account || !$parentId) {
            return;
        }

        if ($account->id === $parentId) {
            throw ValidationException::withMessages([
                'parent_id' => 'An account cannot be its own parent.',
            ]);
        }

        $ancestorId = $parentId;
        while ($ancestorId) {
            if ($ancestorId === $account->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'This parent selection would create an account hierarchy loop.',
                ]);
            }

            $ancestorId = Account::query()->whereKey($ancestorId)->value('parent_id');
        }
    }
}
