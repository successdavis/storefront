<?php

namespace App\Models\Loan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanUser extends BaseLoanModel
{
    use HasFactory;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'mobile',
        'account_number',
        'branch_id',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(LoanAccount::class, 'user_id');
    }
}
