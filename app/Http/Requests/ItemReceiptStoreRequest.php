<?php
declare(strict_types=1);

namespace App\Http\Requests\ItemReceipt;

use Illuminate\Foundation\Http\FormRequest;

class ItemReceiptStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'receipt_number' => ['required','string','max:50'],
            'warehouse_id'   => ['required','exists:warehouses,id'],
            'received_date'  => ['required','date'],
            'items'          => ['required','array','min:1'],
            'items.*.purchase_order_item_id' => ['nullable','exists:purchase_order_items,id'],
            'items.*.product_variant_id'     => ['required','exists:product_variants,id'],
            'items.*.quantity_received'      => ['required','integer','min:1'],
            'items.*.unit_cost'              => ['required','numeric','min:0'],
        ];
    }
}
