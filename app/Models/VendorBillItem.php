<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vendor_bill_id
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property int|null $purchase_order_item_id
 * @property string $description
 * @property float $quantity
 * @property float $unit_cost
 * @property float $discount_amount
 * @property string $type  // product|service|freight|duty|misc
 */
class VendorBillItem extends Model
{
    protected $fillable = [
        'vendor_bill_id',
        'product_id',
        'product_variant_id',
        'purchase_order_item_id',
        'description',
        'quantity',
        'unit_cost',
        'discount_amount',
        'type',
    ];

    protected $casts = [
        'quantity'        => 'decimal:4',
        'unit_cost'       => 'decimal:4',
        'discount_amount' => 'decimal:4',
    ];

    /* -----------------------------------------------------------------
     |  Relationships
     | -----------------------------------------------------------------
     */
    public function vendorBill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /* -----------------------------------------------------------------
     |  Accessors
     | -----------------------------------------------------------------
     */

    /** Line total after discount */
    public function getLineTotalAttribute(): float
    {
        return (float) (($this->quantity * $this->unit_cost) - $this->discount_amount);
    }
}
