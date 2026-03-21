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

        foreach (PermissionNames::all() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        Role::findOrCreate(RoleNames::DIRECTOR, 'web')
            ->syncPermissions(PermissionNames::director());

        Role::findOrCreate(RoleNames::SALES_REPRESENTATIVE, 'web')
            ->syncPermissions(PermissionNames::sales());

        Role::findOrCreate(RoleNames::CUSTOMER, 'web')
            ->syncPermissions(PermissionNames::customer());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = Permission::query()
            ->whereIn('name', PermissionNames::all())
            ->get();

        foreach ([RoleNames::DIRECTOR, RoleNames::SALES_REPRESENTATIVE, RoleNames::CUSTOMER] as $roleName) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->revokePermissionTo($permissions);
            }
        }

        Permission::query()->whereIn('name', PermissionNames::all())->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
