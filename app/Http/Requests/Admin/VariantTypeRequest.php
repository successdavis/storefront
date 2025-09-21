<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VariantTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variantTypeId = optional($this->route('variantType'))->id;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('variant_types', 'name')->ignore($variantTypeId),
            ],
            'values' => ['array'],
            'values.*.id' => ['nullable', 'integer', 'exists:variant_values,id'],
            'values.*.value' => ['required', 'string', 'max:100', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'values.*.value.distinct' => 'Variant values must be unique within the list.',
        ];
    }
}
