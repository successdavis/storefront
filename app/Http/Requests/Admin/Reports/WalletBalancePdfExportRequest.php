<?php

namespace App\Http\Requests\Admin\Reports;

use App\Models\Loan\LoanBranch;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class WalletBalancePdfExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
