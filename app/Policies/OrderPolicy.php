<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Support\PermissionNames;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny([
            PermissionNames::MANAGE_ADMIN_ORDERS,
            PermissionNames::VIEW_ACCOUNT_ORDERS,
            PermissionNames::VIEW_SALES_ORDERS,
        ]);
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->canAny([
            PermissionNames::MANAGE_ADMIN_ORDERS,
            PermissionNames::VIEW_SALES_ORDERS,
        ])) {
            return true;
        }

        return $user->can(PermissionNames::VIEW_ACCOUNT_ORDERS)
            && (int) $order->user_id === (int) $user->id;
    }

    public function manage(User $user, Order $order): bool
    {
        return $user->can(PermissionNames::MANAGE_ADMIN_ORDERS);
    }

    public function manageAny(User $user): bool
    {
        return $user->can(PermissionNames::MANAGE_ADMIN_ORDERS);
    }
}
