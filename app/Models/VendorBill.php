<?php

namespace App\Models;

use App\Traits\HasPayments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{
    BelongsTo,
    HasMany
};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vendor_id
 * @property int|null $purchase_order_id
 * @property string $bill_number
 * @property Carbon $bill_date
 * @property Carbon|null $due_date
 * @property string $status        // unpaid|partially_paid|paid|void
 * @property float $total_amount
 */
class VendorBill extends Model
{
    use HasPayments;

    protected $fillable = [
        'vendor_id',
        'purchase_order_id',
        'bill_number',
        'bill_date',
        'due_date',
        'status',
        'total_amount',
    ];

    protected $casts = [
        'bill_date'   => 'date',
        'due_date'    => 'date',
        'total_amount'=> 'decimal:2',
    ];

    /* -----------------------------------------------------------------
     |  Relationships
     | -----------------------------------------------------------------
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function itemReceipt()
    {
        return $this->belongsTo(ItemReceipt::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorBillItem::class);
    }

    /* -----------------------------------------------------------------
     |  Business Logic
     | -----------------------------------------------------------------
     */

    /** Update the bill status based on payments */
    public function refreshPaymentStatus(): void
    {
        $paid = $this->totalPayments();

        if ($paid <= 0) {
            $this->update(['status' => 'unpaid']);
        } elseif ($paid < (float) $this->total_amount) {
            $this->update(['status' => 'partially_paid']);
        } else {
            $this->update(['status' => 'paid']);
        }
    }

    /* -----------------------------------------------------------------
     |  Scopes
     | -----------------------------------------------------------------
     */
    public function scopeDue(Builder $query, ?Carbon $date = null): Builder
    {
        $date ??= now();
        return $query->where('status', '!=', 'paid')
            ->whereDate('due_date', '<=', $date);
    }
}
