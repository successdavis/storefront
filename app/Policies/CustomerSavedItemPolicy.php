<?php

namespace App\Policies;

use App\Models\CustomerSavedItem;
use App\Models\User;
use App\Support\PermissionNames;

class CustomerSavedItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionNames::MANAGE_ACCOUNT_SAVED_ITEMS);
    }

    public function view(User $user, CustomerSavedItem $customerSavedItem): bool
    {
        return $user->can(PermissionNames::MANAGE_ACCOUNT_SAVED_ITEMS)
            && (int) $customerSavedItem->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionNames::MANAGE_ACCOUNT_SAVED_ITEMS);
    }

    public function update(User $user, CustomerSavedItem $customerSavedItem): bool
    {
        return $this->view($user, $customerSavedItem);
    }

    public function delete(User $user, CustomerSavedItem $customerSavedItem): bool
    {
        return $this->view($user, $customerSavedItem);
    }
}
