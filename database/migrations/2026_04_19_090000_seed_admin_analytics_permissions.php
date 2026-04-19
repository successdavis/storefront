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
            PermissionNames::VIEW_ADMIN_ANALYTICS,
            PermissionNames::MANAGE_ADMIN_ANALYTICS,
        ];
    }

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        Role::findOrCreate(RoleNames::DIRECTOR, 'web')
            ->givePermissionTo($this->permissions());

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
