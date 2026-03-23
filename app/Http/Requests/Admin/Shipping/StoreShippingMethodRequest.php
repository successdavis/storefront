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
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
