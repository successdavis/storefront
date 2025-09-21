<?php

namespace App\Http\Requests\Admin\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockEntryRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'quantity' => ['required','integer','min:1'],
            'unit_cost' => ['required','numeric','min:0'],
            'type' => ['required','in:stock_in,stock_out'],
            'note' => ['nullable','string','max:500'],
        ];
    }
}
