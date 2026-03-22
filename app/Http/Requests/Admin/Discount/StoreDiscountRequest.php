<?php

namespace App\Http\Requests\Admin\Discount;

use App\Models\Discount;
use App\Support\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiscountRequest extends FormRequest
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
            'type' => ['required', 'in:' . implode(',', [
                Discount::TYPE_PERCENTAGE,
                Discount::TYPE_FIXED_AMOUNT,
                Discount::TYPE_FREE_SHIPPING,
            ])],
            'application_method' => ['required', 'in:' . implode(',', [
                Discount::APPLICATION_ORDER_TOTAL,
                Discount::APPLICATION_LINE_ITEM,
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
            'priority' => ['nullable', 'integer', 'min:0', 'max:9999'],
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
            $type = (string) $this->input('type');
            $applicationMethod = (string) $this->input('application_method');

            if ($type !== Discount::TYPE_FREE_SHIPPING && !$this->filled('value')) {
                $validator->errors()->add('value', 'Enter a discount value.');
            }

            if ($type === Discount::TYPE_FREE_SHIPPING && $applicationMethod === Discount::APPLICATION_LINE_ITEM) {
                $validator->errors()->add('application_method', 'Free shipping discounts can only apply to the order total.');
            }

            if ($applicationMethod === Discount::APPLICATION_LINE_ITEM && $type !== Discount::TYPE_FREE_SHIPPING) {
                if ($this->filled('min_order_amount')) {
                    $validator->errors()->add('min_order_amount', 'Line-item discounts cannot use a minimum order amount.');
                }

                if ($this->filled('usage_limit')) {
                    $validator->errors()->add('usage_limit', 'Line-item discounts cannot use a global usage limit.');
                }

                if ($this->filled('usage_limit_per_user')) {
                    $validator->errors()->add('usage_limit_per_user', 'Line-item discounts cannot use a per-user usage limit.');
                }
            }

            if ($type === Discount::TYPE_PERCENTAGE && (float) $this->input('value', 0) > 100) {
                $validator->errors()->add('value', 'Percentage discounts cannot exceed 100%.');
            }

            if ((string) $this->input('customer_scope') === Discount::CUSTOMER_SCOPE_SELECTED && empty($this->input('selected_customer_ids', []))) {
                $validator->errors()->add('selected_customer_ids', 'Select at least one customer for selected-customer discounts.');
            }
        });
    }
}
