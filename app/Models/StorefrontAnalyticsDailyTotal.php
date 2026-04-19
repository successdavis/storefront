<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontAnalyticsDailyTotal extends Model
{
    protected $fillable = [
        'date',
        'page_views',
        'unique_visitors',
        'new_visitors',
        'returning_visitors',
        'guest_page_views',
        'authenticated_page_views',
        'guest_visitors',
        'authenticated_visitors',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'page_views' => 'integer',
            'unique_visitors' => 'integer',
            'new_visitors' => 'integer',
            'returning_visitors' => 'integer',
            'guest_page_views' => 'integer',
            'authenticated_page_views' => 'integer',
            'guest_visitors' => 'integer',
            'authenticated_visitors' => 'integer',
        ];
    }
}
