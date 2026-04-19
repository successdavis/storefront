<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontAnalyticsDailyPage extends Model
{
    protected $fillable = [
        'date',
        'page_path',
        'page_title',
        'component',
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
