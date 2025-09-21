<?php // ProductFaq
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFaq extends Model
{
    protected $fillable = [
        'product_id','product_variant_id','question','answer','is_active','position',
        'helpful_yes','helpful_no','slug','locale'
    ];

    protected $casts = [
        'is_active'   => 'bool',
        'position'    => 'int',
        'helpful_yes' => 'int',
        'helpful_no'  => 'int',
    ];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}
