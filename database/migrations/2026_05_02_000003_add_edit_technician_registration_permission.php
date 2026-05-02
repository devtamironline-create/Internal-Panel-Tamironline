<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * permission جداگانه برای ویرایش اطلاعات درخواست تکنسین.
 *
 * کاربری که این permission را ندارد، فقط می‌تواند تأیید/رد/بایگانی کند
 * و فیلدهای اطلاعاتی (مرحله، یادداشت، شماره قرارداد، …) قفل می‌شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(
            ['name' => 'edit-technician-registration', 'guard_name' => 'web']
        );

        // به‌صورت پیش‌فرض فقط به admin بدهیم
        $admin = Role::where('name', 'admin')->first();
        if ($admin && !$admin->hasPermissionTo('edit-technician-registration')) {
            $admin->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', 'edit-technician-registration')->delete();
    }
};
