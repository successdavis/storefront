<?php

namespace App\Policies;

use App\Models\CustomerAddress;
use App\Models\User;
use App\Support\PermissionNames;

class CustomerAddressPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionNames::MANAGE_ACCOUNT_ADDRESSES);
    }

    public function view(User $user, CustomerAddress $customerAddress): bool
    {
        return $user->can(PermissionNames::MANAGE_ACCOUNT_ADDRESSES)
            && (int) $customerAddress->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionNames::MANAGE_ACCOUNT_ADDRESSES);
    }

    public function update(User $user, CustomerAddress $customerAddress): bool
    {
        return $this->view($user, $customerAddress);
    }

    public function delete(User $user, CustomerAddress $customerAddress): bool
    {
        return $this->view($user, $customerAddress);
    }
}
