<?php

namespace App\Models\Loan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanBranch extends BaseLoanModel
{
    use HasFactory;

    protected $table = 'branches';

    protected $fillable = [
        'name',
        'code',
        'state',
        'lga',
        'country',
        'address',
        'established_date',
        'contact_number',
        'head_office',
    ];

    protected function casts(): array
    {
        return [
            'head_office' => 'boolean',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(LoanAccount::class, 'branch_id');
    }
}
