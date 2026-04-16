<?php

namespace App\Http\Requests\Admin\Product;

use App\Support\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionNames::MANAGE_ADMIN_CATALOG) ?? false;
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:2000'],
        ];
    }
}
