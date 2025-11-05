<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = ['name','code', 'address', 'lga_id', 'state_id', 'contact_person','phone', 'email', 'country_id', 'active'];

    protected $casts = ['active' => 'boolean'];
    public function stockEntries()
    {
        return $this->hasMany(StockEntry::class);
    }

    public function employee()
    {
        return $this->belongsToMany(User::class, 'employee_warehouse');
    }

    public function posTerminals()
    {
        return $this->hasMany(PosTerminal::class);
    }
}
