<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCostAdjustment extends Model
{
    protected $fillable = [
        'product_variant_id',
        'vendor_bill_id',
        'purchase_order_item_id',
        'quantity',
        'old_unit_cost',
        'new_unit_cost',
        'difference_per_unit',
        'total_adjustment',
        'clearing_account',
        'notes',
    ];

    /**
     * Relationships
     */
    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function vendorBill()
    {
        return $this->belongsTo(VendorBill::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /**
     * Scope: Adjustments for a given product.
     */
    public function scopeForProduct($query, int $variantId)
    {
        return $query->where('product_variant_id', $variantId);
    }

    /**
     * Create an adjustment record from cost difference.
     */
    public static function recordAdjustment(
        int $variantId,
        float $quantity,
        float $oldCost,
        float $newCost,
        ?int $vendorBillId = null,
        ?int $poItemId = null,
        ?string $clearingAccount = 'GRNI Clearing',
        ?string $notes = null
    ): self {
        $diff = $newCost - $oldCost;
        $total = $diff * $quantity;

        return self::create([
            'product_variant_id'   => $variantId,
            'vendor_bill_id'       => $vendorBillId,
            'purchase_order_item_id' => $poItemId,
            'quantity'             => $quantity,
            'old_unit_cost'        => $oldCost,
            'new_unit_cost'        => $newCost,
            'difference_per_unit'  => $diff,
            'total_adjustment'     => $total,
            'clearing_account'     => $clearingAccount,
            'notes'                => $notes,
        ]);
    }
}
