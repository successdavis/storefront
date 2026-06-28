<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\MediaUrl;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo',
        'slug',
        'meta_title',
        'meta_description',
        'description',
        'top_brand',
    ];

    protected $casts = [
        'top_brand' => 'boolean',
    ];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        return MediaUrl::make($this->logo);
    }

    /**
     * 🔗 A brand can have many products
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * ✅ Check if brand has any products
     */
    public function hasProducts(): bool
    {
        return $this->products()->exists();
    }

    /**
     * ✅ Get count of products linked to this brand
     */
    public function productCount(): int
    {
        return $this->products()->count();
    }

    /**
     * ✅ Scope: Filter brands by name
     */
    public function scopeSearch($query, $term)
    {
        return $query->where('name', 'like', '%' . $term . '%');
    }
}
