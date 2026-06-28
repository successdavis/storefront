<?php

namespace App\Models\Admin;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $fillable = ['product_id','path','responsive_paths','alt','is_primary','sort_order'];


    protected $casts = [
        'is_primary' => 'boolean',
        'responsive_paths' => 'array',
    ];


    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Accessor to return full image URL instead of relative path
//    public function getPathAttribute($value)
//    {
//        // Ensure the path exists before generating the URL
//        if ($value && !str_starts_with($value, 'http')) {
//            return asset($value);
//            // Example result: https://yourdomain.com/storage/products/101/filename.jpg
//        }
//
//        return $value;
//    }
}
