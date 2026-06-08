<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * مجوزهای یکپارچه برای پنل reviews (audio + text).
 * مجوزهای قدیمی (manage-site-testimonials, manage-site-device-reviews, view-site-device-reviews)
 * نگه داشته می‌شوند تا نقش‌های موجود کار کنند.
 */
return new class extends Migration
{
    private array $permissions = [
        'view-site-reviews',
        'manage-site-reviews',
    ];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            foreach ($this->permissions as $name) {
                if (! $admin->hasPermissionTo($name)) {
                    $admin->givePermissionTo($name);
                }
            }
        }

        // انتقال خودکار: هر role که قبلاً manage-site-testimonials یا
        // manage-site-device-reviews داشت، manage-site-reviews هم بگیرد.
        $legacyManage = ['manage-site-testimonials', 'manage-site-device-reviews'];
        $legacyView = ['view-site-device-reviews'];

        foreach (Role::all() as $role) {
            foreach ($legacyManage as $legacy) {
                if ($role->hasPermissionTo($legacy) && ! $role->hasPermissionTo('manage-site-reviews')) {
                    $role->givePermissionTo('manage-site-reviews');
                    break;
                }
            }
            foreach ($legacyView as $legacy) {
                if ($role->hasPermissionTo($legacy) && ! $role->hasPermissionTo('view-site-reviews')) {
                    $role->givePermissionTo('view-site-reviews');
                    break;
                }
            }
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::whereIn('name', $this->permissions)->delete();
    }
};
