<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'note' => ['nullable', 'string', 'max:1000'],
            'counts' => ['required', 'array', 'min:1'],
            'counts.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'counts.*.physical_quantity' => ['required', 'integer', 'min:0'],
        ];
    }
}
