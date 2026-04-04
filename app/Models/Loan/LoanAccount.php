<?php

namespace App\Models\Loan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanAccount extends BaseLoanModel
{
    use HasFactory;

    protected $table = 'accounts';

    protected $fillable = [
        'interest',
        'user_id',
        'duration',
        'amount',
        'duration_period',
        'pay_period',
        'account_number',
        'status',
        'locked',
        'account_type',
        'purpose',
        'branch_id',
        'pnd',
        'cso',
    ];

    protected function casts(): array
    {
        return [
            'interest' => 'decimal:2',
            'amount' => 'float',
            'status' => 'boolean',
            'locked' => 'boolean',
            'pnd' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(LoanBranch::class, 'branch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(LoanUser::class, 'user_id');
    }
}
