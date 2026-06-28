<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use App\Support\MediaUrl;
use App\Services\ImageOptimizationService;

class Category extends Model
{
    use HasFactory, Notifiable;

    protected $appends = [
        'banner_url',
        'banner_responsive_urls',
        'icon_url',
        'icon_responsive_urls',
        'cover_image_url',
        'cover_image_responsive_urls',
    ];

    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'order',
        'featured',
        'meta_title',
        'meta_description',
        'slug',
        'banner',
        'banner_responsive_paths',
        'icon',
        'icon_responsive_paths',
        'cover_image',
        'cover_image_responsive_paths',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'banner_responsive_paths' => 'array',
        'icon_responsive_paths' => 'array',
        'cover_image_responsive_paths' => 'array',
    ];

    public function getBannerUrlAttribute(): ?string
    {
        return $this->mediaUrl($this->banner);
    }

    public function getBannerResponsiveUrlsAttribute(): array
    {
        return $this->responsiveUrls($this->banner_responsive_paths, $this->banner);
    }

    public function getIconUrlAttribute(): ?string
    {
        return $this->mediaUrl($this->icon);
    }

    public function getIconResponsiveUrlsAttribute(): array
    {
        return $this->responsiveUrls($this->icon_responsive_paths, $this->icon);
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->mediaUrl($this->cover_image);
    }

    public function getCoverImageResponsiveUrlsAttribute(): array
    {
        return $this->responsiveUrls($this->cover_image_responsive_paths, $this->cover_image);
    }

    protected function mediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return MediaUrl::make($path);
    }

    protected function responsiveUrls(?array $variants, ?string $fallbackPath): array
    {
        return app(ImageOptimizationService::class)
            ->toResponsiveUrls($variants, $fallbackPath);
    }

    /**
     * 🔗 Parent category (if this is a sub-category)
     */
    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * 🔗 Subcategories (if this is a parent category)
     */
    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }

    /**
     * 🔗 Products directly under this category
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'category_product');
    }

    /**
     * ✅ Check if the category is a parent category
     */
    public function isParent(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * ✅ Recursively get all descendants (children and grandchildren...)
     */
    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }

    /**
     * ✅ Recursively get the full hierarchy of this category's parents
     */
    public function getParentTree(): array
    {
        $tree = [];
        $parent = $this->parent;

        while ($parent) {
            $tree[] = $parent;
            $parent = $parent->parent;
        }

        return array_reverse($tree);
    }
}
