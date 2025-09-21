<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Category extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'description',
        'parent_id'
    ];

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
