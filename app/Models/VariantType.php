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
        'slug',
    ];

    protected static function booted(): void
    {
        static::creating(function (VariantType $type) {
            if (blank($type->slug)) {
                $type->slug = static::generateUniqueSlug($type->name);
            }
        });

        static::updating(function (VariantType $type) {
            if (blank($type->slug)) {
                $type->slug = static::generateUniqueSlug($type->name, $type->id);
            }
        });
    }

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

    protected static function generateUniqueSlug(?string $name, ?int $ignoreId = null): string
    {
        $base = \Illuminate\Support\Str::slug((string) $name, '_');
        $base = $base !== '' ? $base : 'filter';
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$base}_{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
