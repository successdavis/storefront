<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontAnalyticsDailyGeo extends Model
{
    protected $fillable = [
        'date',
        'country_code',
        'country_name',
        'region_name',
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
