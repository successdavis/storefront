<?php

namespace App\Models;

use App\Traits\HasPayments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInvoice extends Model
{
    use HasFactory, HasPayments;

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';

    protected $fillable = [
        'invoice_number',
        'order_id',
        'sale_id',
        'customer_id',
        'employee_id',
        'pos_terminal_id',
        'currency',
        'total_amount',
        'amount_paid',
        'outstanding_balance',
        'due_date',
        'repayment_terms',
        'status',
        'issued_at',
        'closed_at',
        'meta',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'due_date' => 'date',
        'issued_at' => 'datetime',
        'closed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function posTerminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class);
    }

    public function refreshPaymentStatus(): void
    {
        $paidAmount = round((float) $this->payments()->where('status', 'paid')->sum('amount'), 2);
        $outstanding = round(max(0, (float) $this->total_amount - $paidAmount), 2);

        $status = match (true) {
            $outstanding <= 0 => self::STATUS_PAID,
            $paidAmount > 0 => self::STATUS_PARTIALLY_PAID,
            default => self::STATUS_UNPAID,
        };

        if ($outstanding > 0 && $this->due_date && $this->due_date->isPast()) {
            $status = self::STATUS_OVERDUE;
        }

        $this->forceFill([
            'amount_paid' => $paidAmount,
            'outstanding_balance' => $outstanding,
            'status' => $status,
            'closed_at' => $outstanding <= 0 ? ($this->closed_at ?? now()) : null,
        ])->save();
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OVERDUE;
    }
}
