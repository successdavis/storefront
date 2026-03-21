<?php

use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        foreach (RoleNames::all() as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        User::query()
            ->with('roles')
            ->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    if ($user->hasRole(RoleNames::DIRECTOR)) {
                        continue;
                    }

                    $isStaff = $user->warehouses()->exists();

                    if ($isStaff) {
                        if (!$user->hasRole(RoleNames::SALES_REPRESENTATIVE)) {
                            $user->assignRole(RoleNames::SALES_REPRESENTATIVE);
                        }

                        continue;
                    }

                    if (!$user->hasRole(RoleNames::CUSTOMER)) {
                        $user->assignRole(RoleNames::CUSTOMER);
                    }
                }
            });
    }

    public function down(): void
    {
        User::query()
            ->with('roles')
            ->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    $user->removeRole(RoleNames::SALES_REPRESENTATIVE);
                    $user->removeRole(RoleNames::CUSTOMER);
                }
            });
    }
};
