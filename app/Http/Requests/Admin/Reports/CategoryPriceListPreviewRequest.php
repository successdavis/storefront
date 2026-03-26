<?php

namespace App\Http\Requests\Admin\Reports;

use Illuminate\Foundation\Http\FormRequest;

class CategoryPriceListPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'in_stock_only' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:alphabetical,latest'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
