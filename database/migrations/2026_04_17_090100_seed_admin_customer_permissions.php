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
            PermissionNames::VIEW_ADMIN_CUSTOMERS,
            PermissionNames::VIEW_ADMIN_CUSTOMER_DETAILS,
            PermissionNames::UPDATE_ADMIN_CUSTOMERS,
            PermissionNames::CHANGE_ADMIN_CUSTOMER_STATUS,
            PermissionNames::EMAIL_ADMIN_CUSTOMERS,
            PermissionNames::EXPORT_ADMIN_CUSTOMERS,
            PermissionNames::MANAGE_ADMIN_CUSTOMER_NOTES,
            PermissionNames::BULK_ADMIN_CUSTOMER_ACTIONS,
        ];
    }

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
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
            ->get();

        $director = Role::query()
            ->where('name', RoleNames::DIRECTOR)
            ->where('guard_name', 'web')
            ->first();

        if ($director) {
            $director->revokePermissionTo($permissions);
        }

        Permission::query()->whereIn('name', $this->permissions())->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
