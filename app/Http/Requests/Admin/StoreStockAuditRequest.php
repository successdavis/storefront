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
            'session_id' => ['nullable', 'integer', 'exists:stock_audit_sessions,id'],
            'scope_type' => ['nullable', 'in:full,category'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id', 'required_if:scope_type,category'],
            'submit_anyway' => ['nullable', 'boolean'],
            'source' => ['nullable', 'string', 'in:audit,mobile,manual,system'],
            'counts' => ['required', 'array', 'min:1'],
            'counts.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'counts.*.physical_quantity' => ['required', 'integer', 'min:0'],
        ];
    }
}
