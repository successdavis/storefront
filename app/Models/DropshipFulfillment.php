<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DropshipFulfillment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending_supplier_order';
    public const STATUS_ORDERED = 'ordered_from_supplier';
    public const STATUS_CONFIRMED = 'supplier_confirmed';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_SHIPPED = 'shipped_to_customer';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_UNAVAILABLE = 'unavailable';

    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ORDERED,
        self::STATUS_CONFIRMED,
        self::STATUS_RECEIVED,
        self::STATUS_SHIPPED,
    ];

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ORDERED,
        self::STATUS_CONFIRMED,
        self::STATUS_RECEIVED,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
        self::STATUS_UNAVAILABLE,
    ];

    protected $fillable = [
        'order_id',
        'order_item_id',
        'supplier_id',
        'supplier_cost',
        'status',
        'supplier_reference',
        'ordered_at',
        'confirmed_at',
        'expected_delivery_at',
        'received_at',
        'shipped_to_customer_at',
        'delivered_at',
        'cancelled_at',
        'admin_note',
        'meta',
    ];

    protected $casts = [
        'supplier_cost' => 'decimal:2',
        'ordered_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'expected_delivery_at' => 'datetime',
        'received_at' => 'datetime',
        'shipped_to_customer_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'meta' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'supplier_id');
    }
}
