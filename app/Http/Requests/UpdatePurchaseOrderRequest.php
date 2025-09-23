<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor_id'     => ['sometimes','exists:vendors,id'],
            'warehouse_id'  => ['sometimes','exists:warehouses,id'],
            'order_date'    => ['sometimes','date'],
            'expected_date' => ['nullable','date','after_or_equal:order_date'],
            'note'          => ['nullable','string','max:1000'],
            'items'         => ['sometimes','array','min:1'],
            'items.*.id'                => ['sometimes','exists:purchase_order_items,id'],
            'items.*.product_variant_id'=> ['required_with:items.*.quantity_ordered','exists:product_variants,id'],
            'items.*.quantity_ordered'  => ['required_with:items.*.product_variant_id','integer','min:1'],
            'items.*.unit_cost'         => ['required_with:items.*.product_variant_id','numeric','min:0'],
        ];
    }
}
