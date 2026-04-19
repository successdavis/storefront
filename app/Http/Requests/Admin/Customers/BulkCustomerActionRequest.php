<?php

namespace App\Http\Requests\Admin\Customers;

use App\Services\CustomerManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkCustomerActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_ids' => ['required', 'array', 'min:1'],
            'customer_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'action' => ['required', Rule::in([
                CustomerManagementService::BULK_ACTION_ACTIVATE,
                CustomerManagementService::BULK_ACTION_DEACTIVATE,
                CustomerManagementService::BULK_ACTION_SUSPEND,
                CustomerManagementService::BULK_ACTION_MARK_VIP,
                CustomerManagementService::BULK_ACTION_CLEAR_VIP,
                CustomerManagementService::BULK_ACTION_FLAG_RISK,
                CustomerManagementService::BULK_ACTION_CLEAR_RISK,
            ])],
        ];
    }
}
