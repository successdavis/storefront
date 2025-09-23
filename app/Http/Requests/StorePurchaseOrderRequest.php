<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor_id'      => ['required','exists:vendors,id'],
            'warehouse_id'   => ['required','exists:warehouses,id'],
            'order_date'     => ['required','date'],
            'expected_date'  => ['nullable','date','after_or_equal:order_date'],
            'note'           => ['nullable','string','max:1000'],
            'items'          => ['required','array','min:1'],
            'items.*.product_variant_id' => ['required','exists:product_variants,id'],
            'items.*.quantity_ordered'   => ['required','integer','min:1'],
            'items.*.unit_cost'          => ['required','numeric','min:0'],
        ];
    }
}
