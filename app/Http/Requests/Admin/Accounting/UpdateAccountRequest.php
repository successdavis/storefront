<?php

namespace App\Http\Requests\Admin\Accounting;

use App\Models\Accounting\Account;
use App\Support\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionNames::MANAGE_ADMIN_ACCOUNTING) ?? false;
    }

    public function rules(): array
    {
        /** @var Account|null $account */
        $account = $this->route('account');

        return [
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('accounts', 'code')->ignore($account?->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('accounts', 'slug')->ignore($account?->id)],
            'type' => ['sometimes', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'subtype' => ['nullable', 'string', 'max:50'],
            'classification' => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['nullable', 'boolean'],
            'allows_manual_entries' => ['nullable', 'boolean'],
            'currency' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string'],
        ];
    }
}
