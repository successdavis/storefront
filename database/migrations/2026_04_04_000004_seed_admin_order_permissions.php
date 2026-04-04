<?php

use App\Support\PermissionNames;
use App\Support\RoleNames;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate(PermissionNames::MANAGE_ADMIN_ORDERS, 'web');

        $director = Role::findOrCreate(RoleNames::DIRECTOR, 'web');
        $director->givePermissionTo(PermissionNames::MANAGE_ADMIN_ORDERS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::query()->where('name', RoleNames::DIRECTOR)->where('guard_name', 'web')->first();
        if ($role && $role->hasPermissionTo(PermissionNames::MANAGE_ADMIN_ORDERS)) {
            $role->revokePermissionTo(PermissionNames::MANAGE_ADMIN_ORDERS);
        }

        Permission::query()
            ->where('name', PermissionNames::MANAGE_ADMIN_ORDERS)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
