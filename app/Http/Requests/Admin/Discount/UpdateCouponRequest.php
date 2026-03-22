<?php

namespace App\Http\Requests\Admin\Discount;

use Illuminate\Validation\Rule;

class UpdateCouponRequest extends StoreCouponRequest
{
    public function rules(): array
    {
        $discount = $this->route('coupon');

        return array_merge(parent::rules(), [
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('discounts', 'code')->ignore($discount?->id),
            ],
        ]);
    }
}
