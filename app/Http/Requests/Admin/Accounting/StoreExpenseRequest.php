<?php

namespace App\Http\Requests\Admin\Accounting;

use App\Support\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionNames::MANAGE_ADMIN_ACCOUNTING_EXPENSES) ?? false;
    }

    public function rules(): array
    {
        return [
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'expense_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'payment_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ];
    }
}
