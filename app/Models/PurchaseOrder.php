<?php
declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\CarbonInterface;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'warehouse_id',
        'po_number',
        'order_date',
        'expected_date',
        'status',
        'total_amount',
        'note',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    // -- relations
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function vendorBills(): HasMany
    {
        return $this->hasMany(VendorBill::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function itemReceipts(): HasMany
    {
        return $this->hasMany(ItemReceipt::class);
    }

    // convenience
    public function statusEnum(): PurchaseOrderStatus
    {
        return PurchaseOrderStatus::from($this->status);
    }

    public function isEditable(): bool
    {
        return !in_array($this->status, [
            PurchaseOrderStatus::CANCELLED->value,
            PurchaseOrderStatus::CLOSED->value,
        ], true);
    }

    public function totalOrderedQuantity(): int
    {
        return (int) $this->items()->sum('quantity_ordered');
    }

    public function totalReceivedQuantity(): int
    {
        return (int) $this->items()->sum('quantity_received');
    }

    public function allItemsFullyReceived(): bool
    {
        foreach ($this->items as $it) {
            if ($it->quantity_received < $it->quantity_ordered) {
                return false;
            }
        }
        return true;
    }

    public function outstandingAmount(): float
    {
        // ensure bills loaded to avoid queries when possible
        $bills = $this->relationLoaded('vendorBills') ? $this->vendorBills : $this->vendorBills()->get();
        $out = 0.0;
        foreach ($bills as $bill) {
            $payments = $bill->relationLoaded('payments') ? $bill->payments : $bill->payments()->get();
            $paid = $payments->sum('amount');
            $out += max(0, $bill->total_amount - $paid);
        }
        return (float) $out;
    }
}
