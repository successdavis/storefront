<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariantValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'variant_type_id',
        'value',
    ];

    /**
     * 🔗 Each variant value belongs to a variant type
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(VariantType::class, 'variant_type_id');
    }
}
