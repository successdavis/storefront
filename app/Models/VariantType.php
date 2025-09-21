<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VariantType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * 🔗 A variant type has many variant values
     */
    public function values(): HasMany
    {
        return $this->hasMany(VariantValue::class);
    }

    /**
     * ✅ Check if a variant type has any values
     */
    public function hasValues(): bool
    {
        return $this->values()->exists();
    }

    /**
     * ✅ Scope: filter by name (case-insensitive)
     */
    public function scopeSearch($query, $term)
    {
        return $query->where('name', 'like', '%' . $term . '%');
    }
}
