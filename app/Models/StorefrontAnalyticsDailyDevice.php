<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontAnalyticsDailyDevice extends Model
{
    protected $fillable = [
        'date',
        'device_type',
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
