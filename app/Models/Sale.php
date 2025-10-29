<?php

namespace App\Models;

use App\Traits\HasPayments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory, HasPayments;

    protected $fillable = [
        'employee_id', 'pos_terminal_id', 'user_id', 'total_amount', 'discount', 'customer_id', 'order_id'
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function posTerminal()
    {
        return $this->belongsTo(PosTerminal::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }
}
