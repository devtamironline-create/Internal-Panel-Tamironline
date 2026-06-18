<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Seo\Support\SeoPermissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * دسترسی‌های ریزدانهٔ سئو (بخش ۳۳ مشخصات).
 *
 * - پنج دسترسیِ ریزدانه را به‌صورتِ idempotent می‌سازد.
 * - هر نقشی که اکنون manage-seo دارد، هر پنج دسترسی را هم می‌گیرد (دسترسیِ موجود دست‌نخورده می‌ماند).
 * - پنج نقشِ پیشنهادی را seed کرده و دسترسی‌ها (به‌همراه manage-seo برای عبور از gate مسیرها) را اختصاص می‌دهد.
 *
 * manage-seo هرگز حذف نمی‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ۱) ساختِ idempotent پنج دسترسی ریزدانه (guard web).
        $granular = [];
        foreach (SeoPermissions::keys() as $name) {
            $granular[$name] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // ۲) هر نقشی که manage-seo دارد، هر پنج دسترسی ریزدانه را هم بگیرد (حفظِ دسترسیِ فعلی).
        foreach (Role::all() as $role) {
            if ($role->hasPermissionTo(SeoPermissions::MANAGE)) {
                foreach ($granular as $name => $permission) {
                    if (! $role->hasPermissionTo($name)) {
                        $role->givePermissionTo($permission);
                    }
                }
            }
        }

        // ۳) seed نقش‌های پیشنهادی و اختصاص دسترسی‌ها (شاملِ manage-seo).
        foreach (SeoPermissions::roleMap() as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            foreach ($permissions as $name) {
                $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                if (! $role->hasPermissionTo($name)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // فقط پنج دسترسی ریزدانه حذف می‌شوند؛ manage-seo دست‌نخورده می‌ماند.
        Permission::whereIn('name', SeoPermissions::keys())->delete();

        // نقش‌های seed‌شده تنها در صورتی حذف می‌شوند که هیچ کاربری به آن‌ها اختصاص نیافته باشد.
        foreach (array_keys(SeoPermissions::roleMap()) as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role && $role->users()->count() === 0) {
                $role->delete();
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
