<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Add your own authorization logic if needed
        return true;
    }

    public function rules(): array
    {
        return [
            'type'       => 'required|in:in,out',   // stockin or stockout
            'quantity'   => 'required|integer|min:1',
            'note'       => 'nullable|string|max:255',
        ];
    }
}
