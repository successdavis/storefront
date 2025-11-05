<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeWarehouse extends Model
{
    protected $table = 'employee_warehouse';

    protected $fillable = [
        'warehouse_id',
        'employee_id',
    ];

    public function employee()
    {
        return $this->belongsTo(\App\Models\User::class, 'employee_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(\App\Models\Warehouse::class, 'warehouse_id');
    }
}
