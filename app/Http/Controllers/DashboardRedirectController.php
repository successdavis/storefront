<?php

namespace App\Http\Controllers;

use App\Support\PermissionNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->can(PermissionNames::VIEW_ADMIN_DASHBOARD)) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->can(PermissionNames::VIEW_SALES_DASHBOARD)) {
            return redirect()->route('sales.dashboard');
        }

        if ($user->can(PermissionNames::VIEW_ACCOUNT_DASHBOARD)) {
            return redirect()->route('account.dashboard');
        }

        abort(403);
    }
}
