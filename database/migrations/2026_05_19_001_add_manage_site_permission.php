<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(
            ['name' => 'manage-site', 'guard_name' => 'web']
        );

        // دسترسی به ادمین بده
        $admin = Role::where('name', 'admin')->first();
        if ($admin && !$admin->hasPermissionTo('manage-site')) {
            $admin->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', 'manage-site')->delete();
    }
};
