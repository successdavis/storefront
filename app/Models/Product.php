<?php

namespace App\Models;

use App\Models\Admin\ProductImage;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */

    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'cash_on_delivery' => 'boolean',
        'featured' => 'boolean',
        'is_active' => 'boolean',
        'weight' => 'decimal:3',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
    ];

    // --------------------------
    // 🔗 RELATIONSHIPS
    // --------------------------

    /**
     * Each product may belong to a category (nullable)
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }

    /**
     * Each product may belong to a brand (nullable)
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * A product has many variants (sizes, colors, etc.)
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public static function boot()
    {
        parent::boot();
        static::observe(\App\Observers\ProductObserver::class);
    }

    public function faqs(): HasMany     {
        return $this->hasMany(ProductFaq::class)->orderBy('position');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProductNote::class)->latest();
    }


    public function slugHistories()
    {
        return $this->morphMany(SlugHistory::class, 'sluggable');
    }

    // --------------------------
    // ✅ SCOPES
    // --------------------------

    /**
     * Scope to filter only active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Search products by name (case-insensitive)
     */
    public function scopeSearch($query, $term)
    {
        return $query->where('name', 'like', '%' . $term . '%');
    }

    // --------------------------
    // ✅ CUSTOM METHODS
    // --------------------------

    /**
     * Check if product has any variants
     */
    public function hasVariants(): bool
    {
        return $this->variants()->exists();
    }

    /**
     * Get total inventory quantity from all variants
     */
    public function totalInventory(): int
    {
        return $this->variants()->sum('quantity');
    }

    /**
     * Get the lowest price among its variants
     */
    public function minPrice(): float
    {
        return (float) $this->variants()->min('regular_price');
    }

    /**
     * Get the highest price among its variants
     */
    public function maxPrice(): float
    {
        return (float) $this->variants()->max('regular_price');
    }
}


