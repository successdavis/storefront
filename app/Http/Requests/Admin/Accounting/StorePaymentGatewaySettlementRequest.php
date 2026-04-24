<?php

namespace App\Http\Requests\Admin\Accounting;

use App\Models\Accounting\Account;
use App\Support\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePaymentGatewaySettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionNames::MANAGE_ADMIN_ACCOUNTING) ?? false;
    }

    public function rules(): array
    {
        return [
            'gateway' => ['required', 'string', 'max:50'],
            'settlement_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'bank_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'clearing_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ((int) $this->input('bank_account_id') === (int) $this->input('clearing_account_id')) {
                $validator->errors()->add('bank_account_id', 'The bank account and clearing account must be different.');
            }

            $bankAccount = Account::query()
                ->whereKey($this->input('bank_account_id'))
                ->first(['id', 'type', 'subtype', 'is_active']);

            if (!$bankAccount || !$bankAccount->is_active || $bankAccount->type !== 'asset' || $bankAccount->subtype !== 'bank') {
                $validator->errors()->add('bank_account_id', 'Select a valid active bank account for the settlement destination.');
            }

            $clearingAccount = Account::query()
                ->whereKey($this->input('clearing_account_id'))
                ->first(['id', 'type', 'subtype', 'is_active']);

            if (!$clearingAccount || !$clearingAccount->is_active || $clearingAccount->type !== 'asset' || $clearingAccount->subtype !== 'clearing') {
                $validator->errors()->add('clearing_account_id', 'Select a valid active gateway clearing account.');
            }
        });
    }
}
