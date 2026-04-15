<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAuditSession extends Model
{
    public const SCOPE_FULL = 'full';
    public const SCOPE_CATEGORY = 'category';

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVIEWED = 'reviewed';

    protected $fillable = [
        'warehouse_id',
        'scope_type',
        'category_id',
        'status',
        'total_expected_items',
        'total_scanned_items',
        'coverage_percentage',
        'is_partial',
        'started_by',
        'submitted_by',
        'started_at',
        'submitted_at',
        'last_activity_at',
    ];

    protected $casts = [
        'coverage_percentage' => 'decimal:2',
        'is_partial' => 'boolean',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockAuditItem::class, 'session_id');
    }

    public function itemLocks(): HasMany
    {
        return $this->hasMany(StockAuditItemLock::class, 'session_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
