<?php

namespace App\Models;

use App\Models\Admin\VariantImage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id','sku','quantity','barcode','cost_price',
        'regular_price','sale_price','sale_starts_at','sale_ends_at',
        'weight','length','width','height','track_inventory','reorder_point','is_active',
    ];

    protected $casts = [
        'sale_starts_at' => 'datetime',
        'sale_ends_at' => 'datetime',
        'weight' => 'decimal:3',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'track_inventory' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * 🔗 Each product variant belongs to a product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 🔗 Variant values for this specific variant (e.g. Red, Large)
     */

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'variant_id');
    }

    public function values()
    {
        return $this->belongsToMany(VariantValue::class, 'product_variant_values')
            ->withTimestamps();
    }

    public function images()
    {
        return $this->hasMany(VariantImage::class)->orderBy('sort_order');
    }

    public function openingBalanceItems()
    {
        return $this->hasMany(OpeningBalanceItem::class, 'variant_id');
    }

    public function stockEntries()
    {
        return $this->hasMany(StockEntry::class, 'variant_id');
    }

    public function stockAuditItems()
    {
        return $this->hasMany(StockAuditItem::class, 'variant_id');
    }

    /**
     * 🔄 Scope for checking stock availability
     */
    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'variant_id');
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class, 'variant_id');
    }

    public function stockReservations()
    {
        return $this->hasMany(StockReservation::class, 'variant_id');
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'product_variant_id');
    }

    public function itemReceiptItems()
    {
        return $this->hasMany(ItemReceiptItem::class, 'product_variant_id');
    }

    public function vendorBillItems()
    {
        return $this->hasMany(VendorBillItem::class, 'product_variant_id');
    }

    public function inventoryCostAdjustments()
    {
        return $this->hasMany(InventoryCostAdjustment::class, 'product_variant_id');
    }

    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class, 'variant_id');
    }

    public function inventoryAlerts()
    {
        return $this->hasMany(InventoryAlert::class, 'variant_id');
    }

    public function getIsOnSaleAttribute(): bool
    {
        $now = now();
        return $this->sale_price !== null
            && $this->sale_price < $this->regular_price
            && (!$this->sale_starts_at || $this->sale_starts_at <= $now)
            && (!$this->sale_ends_at || $this->sale_ends_at >= $now);
    }

    /**
     * ✅ Update inventory quantity
     */
    public function reduceStock(int $quantity): void
    {
        $this->decrement('quantity', $quantity);
    }


    /**
     * ✅ Generate label text (can be used for barcode/label printing)
     */
    public function label(): string
    {
        $values = $this->values->pluck('value')->implode(', ');
        return "{$this->product->name} - {$values}";
    }

    public function getDisplayNameAttribute(): string
    {
        $values = $this->values
            ->map(fn ($v) => "{$v->type->name} {$v->value}")
            ->implode(' ');

        return trim("{$this->product->name} {$values}");
    }

    public function hasDurableHistory(): bool
    {
        if ((int) $this->quantity > 0 || (int) ($this->reserved ?? 0) > 0) {
            return true;
        }

        return $this->openingBalanceItems()->exists()
            || $this->stockEntries()->exists()
            || $this->orderItems()->exists()
            || $this->saleItems()->exists()
            || $this->stockReservations()->exists()
            || $this->purchaseOrderItems()->exists()
            || $this->itemReceiptItems()->exists()
            || $this->vendorBillItems()->exists()
            || $this->inventoryCostAdjustments()->exists()
            || $this->stockAdjustments()->exists()
            || $this->stockAuditItems()->exists()
            || $this->inventoryAlerts()->exists();
    }
}
