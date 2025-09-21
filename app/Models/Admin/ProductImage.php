<?php

namespace App\Models\Admin;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $fillable = ['product_id','path','alt','is_primary','sort_order'];


    protected $casts = [ 'is_primary' => 'boolean' ];


    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
