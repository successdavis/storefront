<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontAnalyticsDailyVisitor extends Model
{
    protected $fillable = [
        'date',
        'visitor_key',
        'is_authenticated',
        'is_new_visitor',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_authenticated' => 'boolean',
            'is_new_visitor' => 'boolean',
        ];
    }
}
