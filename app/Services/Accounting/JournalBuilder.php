<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use InvalidArgumentException;

class JournalBuilder
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $lines = [];

    public static function make(): self
    {
        return new self();
    }

    public function debit(Account $account, float $amount, ?string $description = null, ?string $entityType = null, ?int $entityId = null, ?array $meta = null): self
    {
        return $this->push($account, $amount, 0, $description, $entityType, $entityId, $meta);
    }

    public function credit(Account $account, float $amount, ?string $description = null, ?string $entityType = null, ?int $entityId = null, ?array $meta = null): self
    {
        return $this->push($account, 0, $amount, $description, $entityType, $entityId, $meta);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function totalDebit(): float
    {
        return round(collect($this->lines)->sum('debit'), 4);
    }

    public function totalCredit(): float
    {
        return round(collect($this->lines)->sum('credit'), 4);
    }

    protected function push(Account $account, float $debit, float $credit, ?string $description, ?string $entityType, ?int $entityId, ?array $meta): self
    {
        $debit = round($debit, 4);
        $credit = round($credit, 4);

        if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
            throw new InvalidArgumentException('A journal line must have exactly one non-zero side.');
        }

        $this->lines[] = [
            'account_id' => $account->id,
            'debit' => $debit,
            'credit' => $credit,
            'description' => $description,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'meta' => $meta,
        ];

        return $this;
    }
}
