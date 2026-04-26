<?php

namespace App\Http\Requests\Admin\Accounting;

use App\Models\Accounting\Account;
use App\Support\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCashBankTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionNames::MANAGE_ADMIN_ACCOUNTING) ?? false;
    }

    public function rules(): array
    {
        return [
            'transfer_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'cash_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'bank_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ((int) $this->input('cash_account_id') === (int) $this->input('bank_account_id')) {
                $validator->errors()->add('cash_account_id', 'The cash account and bank account must be different.');
            }

            $cashAccount = Account::query()
                ->whereKey($this->input('cash_account_id'))
                ->first(['id', 'type', 'subtype', 'is_active']);

            if (!$cashAccount || !$cashAccount->is_active || $cashAccount->type !== 'asset' || $cashAccount->subtype !== 'cash') {
                $validator->errors()->add('cash_account_id', 'Select a valid active cash account as the transfer source.');
            }

            $bankAccount = Account::query()
                ->whereKey($this->input('bank_account_id'))
                ->first(['id', 'type', 'subtype', 'is_active']);

            if (!$bankAccount || !$bankAccount->is_active || $bankAccount->type !== 'asset' || $bankAccount->subtype !== 'bank') {
                $validator->errors()->add('bank_account_id', 'Select a valid active bank account as the transfer destination.');
            }
        });
    }
}
