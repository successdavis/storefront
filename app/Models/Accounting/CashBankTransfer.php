<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashBankTransfer extends Model
{
    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'transfer_number',
        'transfer_date',
        'amount',
        'currency',
        'cash_account_id',
        'bank_account_id',
        'reference',
        'description',
        'note',
        'status',
        'journal_entry_id',
        'recorded_by',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            'amount' => 'decimal:4',
            'meta' => 'array',
        ];
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
