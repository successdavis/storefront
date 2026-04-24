<?php

namespace App\Http\Requests\Admin\Accounting;

use App\Support\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionNames::POST_ADMIN_ACCOUNTING_JOURNALS) ?? false;
    }

    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'in:draft,posted'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
