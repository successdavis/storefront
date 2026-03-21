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
            PermissionNames::VIEW_ACCOUNT_ORDERS,
            PermissionNames::VIEW_SALES_ORDERS,
        ]);
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->can(PermissionNames::VIEW_SALES_ORDERS)) {
            return true;
        }

        return $user->can(PermissionNames::VIEW_ACCOUNT_ORDERS)
            && (int) $order->user_id === (int) $user->id;
    }
}
