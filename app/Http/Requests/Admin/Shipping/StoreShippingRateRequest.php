<?php

namespace App\Http\Requests\Admin\Shipping;

use App\Models\Lga;
use App\Models\ShippingMethod;
use App\Support\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

class StoreShippingRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionNames::MANAGE_ADMIN_CATALOG) ?? false;
    }

    public function rules(): array
    {
        return [
            'shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'scope_type' => ['required', 'in:global,zone,state,lga'],
            'shipping_zone_id' => ['nullable', 'integer', 'exists:shipping_zones,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'lga_id' => ['nullable', 'integer', 'exists:lgas,id'],
            'rate_type' => ['required', 'in:flat,per_kg,hybrid'],
            'base_rate' => ['required', 'numeric', 'min:0'],
            'per_kg' => ['nullable', 'numeric', 'min:0'],
            'surcharge' => ['nullable', 'numeric', 'min:0'],
            'free_shipping_threshold' => ['nullable', 'numeric', 'min:0'],
            'estimated_delivery_text' => ['nullable', 'string', 'max:120'],
            'min_weight' => ['nullable', 'numeric', 'min:0'],
            'max_weight' => ['nullable', 'numeric', 'min:0'],
            'min_subtotal' => ['nullable', 'numeric', 'min:0'],
            'max_subtotal' => ['nullable', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $scopeType = (string) $this->input('scope_type');
            $rateType = (string) $this->input('rate_type');
            $method = ShippingMethod::query()->find((int) $this->input('shipping_method_id'));

            if ($scopeType === 'zone' && !$this->filled('shipping_zone_id')) {
                $validator->errors()->add('shipping_zone_id', 'Select a shipping zone for zone-based rates.');
            }

            if ($scopeType === 'state' && !$this->filled('state_id')) {
                $validator->errors()->add('state_id', 'Select a state for state-based rates.');
            }

            if ($scopeType === 'lga') {
                if (!$this->filled('state_id')) {
                    $validator->errors()->add('state_id', 'Select a state for LGA-based rates.');
                }

                if (!$this->filled('lga_id')) {
                    $validator->errors()->add('lga_id', 'Select an LGA for LGA-based rates.');
                }
            }

            if ($this->filled('lga_id') && $this->filled('state_id')) {
                $lgaBelongsToState = Lga::query()
                    ->whereKey((int) $this->input('lga_id'))
                    ->where('state_id', (int) $this->input('state_id'))
                    ->exists();

                if (!$lgaBelongsToState) {
                    $validator->errors()->add('lga_id', 'Selected LGA does not belong to the chosen state.');
                }
            }

            if ($this->filled('min_weight') && $this->filled('max_weight') && (float) $this->input('max_weight') < (float) $this->input('min_weight')) {
                $validator->errors()->add('max_weight', 'Maximum weight must be greater than or equal to minimum weight.');
            }

            if ($this->filled('min_subtotal') && $this->filled('max_subtotal') && (float) $this->input('max_subtotal') < (float) $this->input('min_subtotal')) {
                $validator->errors()->add('max_subtotal', 'Maximum subtotal must be greater than or equal to minimum subtotal.');
            }

            if ($rateType === 'flat' && $this->filled('per_kg') && (float) $this->input('per_kg') > 0) {
                $validator->errors()->add('per_kg', 'Flat rates cannot charge per kg.');
            }

            if (in_array($rateType, ['per_kg', 'hybrid'], true) && (float) $this->input('per_kg', 0) <= 0 && !$method?->isPickup()) {
                $validator->errors()->add('per_kg', 'Per-kg and hybrid rates must define a per-kg charge.');
            }

            if ($method?->isPickup()) {
                if ((float) $this->input('base_rate', 0) > 0 || (float) $this->input('per_kg', 0) > 0 || (float) $this->input('surcharge', 0) > 0) {
                    $validator->errors()->add('base_rate', 'Pickup rates must remain zero-cost.');
                }
            }
        });
    }
}
