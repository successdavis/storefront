<?php

use App\Support\PermissionNames;
use App\Support\RoleNames;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * @return list<string>
     */
    protected function permissions(): array
    {
        return [
            PermissionNames::VIEW_ADMIN_ACCOUNTING,
            PermissionNames::MANAGE_ADMIN_ACCOUNTING,
            PermissionNames::POST_ADMIN_ACCOUNTING_JOURNALS,
            PermissionNames::VIEW_ADMIN_ACCOUNTING_REPORTS,
            PermissionNames::MANAGE_ADMIN_ACCOUNTING_EXPENSES,
        ];
    }

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $director = Role::findOrCreate(RoleNames::DIRECTOR, 'web');
        $director->givePermissionTo($this->permissions());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = Permission::query()
            ->whereIn('name', $this->permissions())
            ->where('guard_name', 'web')
            ->get();

        $role = Role::query()
            ->where('name', RoleNames::DIRECTOR)
            ->where('guard_name', 'web')
            ->first();

        if ($role) {
            $role->revokePermissionTo($permissions);
        }

        Permission::query()
            ->whereIn('name', $this->permissions())
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
