<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewaySettlement extends Model
{
    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'settlement_number',
        'gateway',
        'settlement_date',
        'amount',
        'currency',
        'bank_account_id',
        'clearing_account_id',
        'reference',
        'status',
        'description',
        'note',
        'journal_entry_id',
        'recorded_by',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'settlement_date' => 'date',
            'amount' => 'decimal:4',
            'meta' => 'array',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }

    public function clearingAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'clearing_account_id');
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
