<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorefrontAnalyticsVisitor extends Model
{
    protected $fillable = [
        'visitor_key',
        'first_user_id',
        'last_user_id',
        'first_page_path',
        'last_page_path',
        'first_referrer_domain',
        'last_referrer_domain',
        'first_country_code',
        'first_country_name',
        'first_region_name',
        'last_country_code',
        'last_country_name',
        'last_region_name',
        'first_device_type',
        'last_device_type',
        'total_page_views',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'total_page_views' => 'integer',
        ];
    }

    public function firstUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'first_user_id');
    }

    public function lastUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_user_id');
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(StorefrontAnalyticsPageView::class, 'visitor_key', 'visitor_key');
    }
}
