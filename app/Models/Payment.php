<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model {

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'meta' => 'array',
    ];

    public function payable() {
        return $this->morphTo();
    }
    
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
