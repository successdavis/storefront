<?php

namespace App\Models;

use App\Enums\StockAdjustmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockAdjustment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const DEFAULT_ADJUSTMENT_TYPE = StockAdjustmentType::CORRECTION->value;

    protected $fillable = [
        'warehouse_id',
        'variant_id',
        'previous_quantity',
        'adjusted_quantity',
        'adjustment_type',
        'reason',
        'employee_id',
        'reference',
        'note',
        'adjusted_at',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'approval_note',
    ];

    protected $casts = [
        'adjustment_type' => StockAdjustmentType::class,
        'adjusted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    // Relationships
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function stockAuditItem()
    {
        return $this->hasOne(StockAuditItem::class, 'stock_adjustment_id');
    }

    public function getNewQuantityAttribute(): int
    {
        return (int) $this->previous_quantity + (int) $this->adjusted_quantity;
    }

    public function adjustmentTypeLabel(): string
    {
        return $this->adjustment_type?->label() ?? ucfirst((string) $this->adjustment_type);
    }
}
