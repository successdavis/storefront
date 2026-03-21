<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSavedItem extends Model
{
    use HasFactory;

    public const TYPE_WISHLIST = 'wishlist';
    public const TYPE_SAVED_FOR_LATER = 'saved_for_later';

    protected $fillable = [
        'user_id',
        'list_type',
        'product_id',
        'variant_id',
        'quantity',
        'price_snapshot',
        'currency',
        'product_name_snapshot',
        'variant_label_snapshot',
        'meta',
    ];

    protected $casts = [
        'price_snapshot' => 'decimal:2',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
