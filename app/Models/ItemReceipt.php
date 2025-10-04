<?php
namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ItemReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'warehouse_id',
        'receipt_number',
        'received_date',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(ItemReceiptItem::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public static function nextReceiptNumber(): string
    {
        $prefix = 'IR-'.Carbon::now()->format('Ymd');
        $seq    = str_pad(
            (string) (static::whereDate('created_at', today())->count() + 1),
            4,
            '0',
            STR_PAD_LEFT
        );

        return $prefix.'-'.$seq;  // e.g. IR-20250924-0001
    }

    public function vendorBillItems()
    {
        return $this->hasMany(VendorBill::class);
    }
}
