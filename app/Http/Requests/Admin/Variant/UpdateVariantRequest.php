<?php

namespace App\Http\Requests\Admin\Variant;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class UpdateVariantRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $variantId = $this->route('variant')->id ?? null;
        return [
            'sku' => ['required','string','max:80', Rule::unique('product_variants','sku')->ignore($variantId)],
            'regular_price' => ['required','numeric','min:0'],
            'sale_price' => ['nullable','numeric','lte:regular_price'],
            'quantity' => ['required','integer','min:0'],
            'value_ids' => ['required','array','min:1'],
            'value_ids.*' => ['integer','exists:variant_values,id'],
        ];
    }
}
