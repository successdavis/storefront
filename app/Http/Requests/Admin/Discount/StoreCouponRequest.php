<?php

namespace App\Http\Requests\Admin\Discount;

use App\Models\Discount;
use App\Support\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionNames::MANAGE_ADMIN_CATALOG) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'code' => ['required', 'string', 'max:64', Rule::unique('discounts', 'code')],
            'type' => ['required', 'in:' . implode(',', [
                Discount::TYPE_PERCENTAGE,
                Discount::TYPE_FIXED_AMOUNT,
                Discount::TYPE_FREE_SHIPPING,
            ])],
            'value' => ['nullable', 'numeric', 'gt:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'customer_scope' => ['required', 'in:' . implode(',', [
                Discount::CUSTOMER_SCOPE_ALL,
                Discount::CUSTOMER_SCOPE_NEW,
                Discount::CUSTOMER_SCOPE_SELECTED,
            ])],
            'is_active' => ['nullable', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'selected_customer_ids' => ['nullable', 'array'],
            'selected_customer_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ((string) $this->input('type') !== Discount::TYPE_FREE_SHIPPING && !$this->filled('value')) {
                $validator->errors()->add('value', 'Enter a discount value.');
            }

            if ((string) $this->input('type') === Discount::TYPE_PERCENTAGE && (float) $this->input('value', 0) > 100) {
                $validator->errors()->add('value', 'Percentage discounts cannot exceed 100%.');
            }

            if ((string) $this->input('customer_scope') === Discount::CUSTOMER_SCOPE_SELECTED && empty($this->input('selected_customer_ids', []))) {
                $validator->errors()->add('selected_customer_ids', 'Select at least one customer for selected-customer coupons.');
            }
        });
    }
}
