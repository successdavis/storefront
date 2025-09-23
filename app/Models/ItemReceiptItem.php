<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ItemReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_receipt_id',
        'purchase_order_item_id',
        'product_variant_id',
        'quantity_received',
        'unit_cost',
        'line_total',
    ];

    protected $casts = [
        'quantity_received' => 'integer',
        'unit_cost' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function itemReceipt()
    {
        return $this->belongsTo(ItemReceipt::class);
    }
}
