<?php

namespace App\Models\Admin;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;

class VariantImage extends Model
{
    protected $fillable = ['product_variant_id','path','alt','is_primary','sort_order'];
    protected $casts = [ 'is_primary' => 'boolean' ];

    public function variant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

}
