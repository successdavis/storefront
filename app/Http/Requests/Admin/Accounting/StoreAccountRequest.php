<?php

namespace App\Http\Requests\Admin\Accounting;

use App\Support\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionNames::MANAGE_ADMIN_ACCOUNTING) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:accounts,code'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:accounts,slug'],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'subtype' => ['nullable', 'string', 'max:50'],
            'classification' => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['nullable', 'boolean'],
            'is_system' => ['nullable', 'boolean'],
            'allows_manual_entries' => ['nullable', 'boolean'],
            'currency' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string'],
        ];
    }
}
