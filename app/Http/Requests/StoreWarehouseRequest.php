<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => 'required|string|max:255',
            'code'           => 'required|string|max:50|unique:warehouses,code',
            'address'        => 'nullable|string|max:255',
            'country_id'     => 'required|integer|exists:countries,id',
            'state_id'       => 'required|integer|exists:states,id',
            'lga_id'         => 'required|integer|exists:lgas,id',
            'contact_person' => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:20',
            'email'          => 'nullable|email|max:100',
            'active'         => 'boolean',
        ];
    }
}
