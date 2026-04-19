<?php

namespace App\Policies;

use App\Models\User;
use App\Support\PermissionNames;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionNames::VIEW_ADMIN_CUSTOMERS);
    }

    public function view(User $user, User $customer): bool
    {
        return $customer->isCustomer() && $user->can(PermissionNames::VIEW_ADMIN_CUSTOMER_DETAILS);
    }

    public function update(User $user, User $customer): bool
    {
        return $customer->isCustomer() && $user->can(PermissionNames::UPDATE_ADMIN_CUSTOMERS);
    }

    public function changeStatus(User $user, User $customer): bool
    {
        return $customer->isCustomer() && $user->can(PermissionNames::CHANGE_ADMIN_CUSTOMER_STATUS);
    }

    public function sendEmail(User $user, User $customer): bool
    {
        return $customer->isCustomer() && $user->can(PermissionNames::EMAIL_ADMIN_CUSTOMERS);
    }

    public function export(User $user): bool
    {
        return $user->can(PermissionNames::EXPORT_ADMIN_CUSTOMERS);
    }

    public function manageNotes(User $user, User $customer): bool
    {
        return $customer->isCustomer() && $user->can(PermissionNames::MANAGE_ADMIN_CUSTOMER_NOTES);
    }

    public function bulkAction(User $user): bool
    {
        return $user->can(PermissionNames::BULK_ADMIN_CUSTOMER_ACTIONS);
    }
}
