<?php

namespace App\Models\Loan;

use Illuminate\Database\Eloquent\Model;

abstract class BaseLoanModel extends Model
{
    protected $connection = 'loan_mysql';
}
