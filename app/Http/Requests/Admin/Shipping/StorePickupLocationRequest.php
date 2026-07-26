<?php

namespace App\Http\Requests\Admin\Shipping;

use App\Models\Lga;
use App\Models\ShippingMethod;
use App\Support\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

class StorePickupLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionNames::MANAGE_ADMIN_CATALOG) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'lga_id' => ['nullable', 'integer', 'exists:lgas,id'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'lead_time_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $method = ShippingMethod::query()->find((int) $this->input('shipping_method_id'));
            if ($method && !$method->isPickup()) {
                $validator->errors()->add('shipping_method_id', 'Pickup locations must belong to a pickup-type shipping method.');
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
        });
    }
}
