<?php

namespace App\Http\Requests\Admin\Shipping;

use App\Models\ShippingMethod;
use App\Support\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShippingMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionNames::MANAGE_ADMIN_CATALOG) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('shipping_methods', 'name')],
            'description' => ['nullable', 'string', 'max:1000'],
            'method_type' => ['required', 'in:' . implode(',', [
                ShippingMethod::TYPE_DELIVERY,
                ShippingMethod::TYPE_PICKUP,
            ])],
            'processing_days_min' => ['nullable', 'integer', 'min:0', 'max:365'],
            'processing_days_max' => ['nullable', 'integer', 'min:0', 'max:365'],
            'transit_days_min' => ['nullable', 'integer', 'min:0', 'max:365'],
            'transit_days_max' => ['nullable', 'integer', 'min:0', 'max:365'],
            'cutoff_time' => ['nullable', 'date_format:H:i'],
            'business_days_only' => ['nullable', 'boolean'],
            'supports_weekend_delivery' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('processing_days_min') && $this->filled('processing_days_max') && (int) $this->input('processing_days_max') < (int) $this->input('processing_days_min')) {
                $validator->errors()->add('processing_days_max', 'Maximum processing days must be greater than or equal to minimum processing days.');
            }

            if ($this->filled('transit_days_min') && $this->filled('transit_days_max') && (int) $this->input('transit_days_max') < (int) $this->input('transit_days_min')) {
                $validator->errors()->add('transit_days_max', 'Maximum transit days must be greater than or equal to minimum transit days.');
            }
        });
    }
}
