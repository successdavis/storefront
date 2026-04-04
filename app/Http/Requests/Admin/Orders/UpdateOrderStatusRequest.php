<?php

namespace App\Http\Requests\Admin\Orders;

use App\Services\OrderManagementService;
use App\Support\PermissionNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionNames::MANAGE_ADMIN_ORDERS) ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in([
                OrderManagementService::ACTION_MARK_PAYMENT_PAID,
                OrderManagementService::ACTION_MARK_PROCESSING,
                OrderManagementService::ACTION_MARK_PACKED,
                OrderManagementService::ACTION_MARK_SHIPPED,
                OrderManagementService::ACTION_MARK_READY_FOR_PICKUP,
                OrderManagementService::ACTION_MARK_DELIVERED,
                OrderManagementService::ACTION_CANCEL,
            ])],
            'note' => ['nullable', 'string', 'max:1000'],
            'payment_amount' => ['nullable', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', Rule::in(OrderManagementService::PAYMENT_METHODS)],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'courier_name' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_url' => ['nullable', 'url', 'max:255'],
        ];
    }
}
