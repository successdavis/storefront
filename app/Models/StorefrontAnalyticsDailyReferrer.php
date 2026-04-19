<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontAnalyticsDailyReferrer extends Model
{
    protected $fillable = [
        'date',
        'referrer_domain',
        'page_views',
        'unique_visitors',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'page_views' => 'integer',
            'unique_visitors' => 'integer',
        ];
    }
}
