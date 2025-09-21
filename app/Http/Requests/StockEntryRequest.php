<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // adjust if using policies/permissions
    }

    public function rules(): array
    {
        // Determine if we are creating or updating
        $isUpdate = in_array($this->method(), ['PUT', 'PATCH']);

        return [
            // If updating, 'sometimes' makes the field optional
            'warehouse_id'     => ['nullable', 'exists:warehouses,id'],
            'variant_id'       => [$isUpdate ? 'sometimes' : 'required', 'exists:product_variants,id'],
            'quantity'         => [$isUpdate ? 'sometimes' : 'required', 'integer', 'min:1'],
            'unit_cost'        => [$isUpdate ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'type'             => [$isUpdate ? 'sometimes' : 'required', 'in:stock_in,stock_out'],
            'effective_at'     => [$isUpdate ? 'sometimes' : 'required', 'date'],
            'reason'           => ['nullable', 'string', 'max:255'],
            'source_type'      => ['nullable', 'string', 'max:255'],
            'source_id'        => ['nullable', 'integer'],
            'track_inventory'  => ['boolean'],
            'employee_id'      => ['nullable', 'exists:employees,id'],
            'note'             => ['nullable', 'string'],
        ];
    }
}
