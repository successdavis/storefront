<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVendorBillRequest extends FormRequest
{
    public function authorize()
    {
        // adapt to your policy: e.g. auth()->user()->can('create', VendorBill::class)
        return $this->user() != null;
    }

    public function rules()
    {
        return [
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'vendor_id' => ['required', 'exists:vendors,id'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['nullable', 'exists:purchase_order_items,id'],
            'items.*.product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'expenses' => ['nullable', 'array'],
            'expenses.*.description' => ['required_with:expenses|string'],
            'expenses.*.amount' => ['required_with:expenses|numeric|min:0'],
        ];
    }

    public function prepareForValidation(): void
    {
        // ensure numeric strings are cast correctly
        $input = $this->all();
        if (isset($input['items']) && is_array($input['items'])) {
            foreach ($input['items'] as $i => $it) {
                $input['items'][$i]['quantity'] = isset($it['quantity']) ? (float)$it['quantity'] : 0;
                $input['items'][$i]['unit_cost'] = isset($it['unit_cost']) ? (float)$it['unit_cost'] : 0;
            }
        }
        $this->replace($input);
    }
}
