<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontAnalyticsPageView extends Model
{
    protected $fillable = [
        'visitor_key',
        'user_id',
        'occurred_on',
        'occurred_at',
        'page_path',
        'page_title',
        'component',
        'country_code',
        'country_name',
        'region_name',
        'device_type',
        'referrer_domain',
        'is_authenticated',
        'is_new_visitor',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'occurred_at' => 'datetime',
            'is_authenticated' => 'boolean',
            'is_new_visitor' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(StorefrontAnalyticsVisitor::class, 'visitor_key', 'visitor_key');
    }
}
