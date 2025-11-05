<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosTerminal extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'location','warehouse_id', 'locked_by_employee_id', 'locked_at'
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
