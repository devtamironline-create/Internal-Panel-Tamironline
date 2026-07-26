<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * دسترسی‌های «بازوی سئو»: seo-arm-view / seo-arm-manage.
 * هم‌الگو با add_granular_seo_permissions: هر نقشی که manage-seo دارد،
 * هر دو دسترسی جدید را هم می‌گیرد تا دسترسیِ فعلی کاربران حفظ شود.
 */
return new class extends Migration
{
    private const KEYS = ['seo-arm-view', 'seo-arm-manage'];

    public function up(): void
    {
        if (! class_exists(Permission::class)) {
            return;
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $created = [];
        foreach (self::KEYS as $name) {
            $created[$name] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (Role::all() as $role) {
            if ($role->hasPermissionTo('manage-seo')) {
                foreach ($created as $name => $permission) {
                    if (! $role->hasPermissionTo($name)) {
                        $role->givePermissionTo($permission);
                    }
                }
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
        Permission::whereIn('name', self::KEYS)->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
