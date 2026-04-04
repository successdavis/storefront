<?php

namespace App\Http\Requests\Admin\Reports;

use App\Models\Loan\LoanBranch;
use App\Support\LoanWalletType;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WalletCategoryAccountsExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wallet_type' => ['required', 'string', Rule::in(LoanWalletType::keys())],
            'branch_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== null && !LoanBranch::query()->whereKey($value)->exists()) {
                        $fail('The selected branch is invalid.');
                    }
                },
            ],
        ];
    }
}
