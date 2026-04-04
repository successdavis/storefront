<?php

namespace App\Http\Requests\Admin\Orders;

use App\Services\OrderManagementService;
use App\Support\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkOrderActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionNames::MANAGE_ADMIN_ORDERS) ?? false;
    }

    public function rules(): array
    {
        return [
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
            'action' => ['required', Rule::in([
                OrderManagementService::ACTION_MARK_PROCESSING,
                OrderManagementService::ACTION_MARK_PACKED,
            ])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
