<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * دسترسی «مدیریت قرارداد کارمندان». به نقش‌هایی که مدیریت پرسنل/دسترسی
 * دارند خودکار داده می‌شود تا مدیر بدون تنظیم دستی، صفحه را ببیند.
 * (نقش admin از طریق Gate::before همیشه دسترسی دارد.)
 */
return new class extends Migration
{
    private const KEY = 'manage-staff-contracts';

    public function up(): void
    {
        if (! class_exists(Permission::class)) {
            return;
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => self::KEY, 'guard_name' => 'web']);

        foreach (Role::all() as $role) {
            if ($role->hasPermissionTo('manage-permissions') && ! $role->hasPermissionTo(self::KEY)) {
                $role->givePermissionTo($permission);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! class_exists(Permission::class)) {
            return;
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::where('name', self::KEY)->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
