<?php
namespace App\Http\Requests\Admin\Variant;


use Illuminate\Foundation\Http\FormRequest;


class StoreVariantRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'variants' => ['required','array','min:1'],
            'variants.*.sku' => ['required','string','max:80','unique:product_variants,sku'],
            'variants.*.regular_price' => ['required','numeric','min:0'],
            'variants.*.quantity' => ['nullable','integer','min:0'],
            'variants.*.value_ids' => ['required','array','min:1'],
            'variants.*.value_ids.*' => ['integer','exists:variant_values,id'],
        ];
    }
}
