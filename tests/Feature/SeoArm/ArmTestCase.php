<?php

namespace Tests\Feature\SeoArm;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * پایهٔ تست‌های بازوی سئو — فقط جدول‌های لازم را روی sqlite حافظه migrate
 * می‌کند (اجرای کل migration های MySQL-محور پروژه روی sqlite ممکن نیست).
 */
abstract class ArmTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2025_12_19_195120_create_permission_tables.php',
            'database/migrations/2026_02_03_140854_create_settings_table.php',
            'database/migrations/2026_01_04_100000_create_chat_tables.php', // layout پنل presence/چت را می‌خواند
            'Modules/Seo/Database/Migrations/2026_06_16_000008_create_seo_change_logs_table.php',
            'Modules/Seo/Database/Migrations/2026_06_16_000002_create_seo_settings_table.php',
            'Modules/Seo/Database/Migrations/2026_07_26_100000_create_seo_arm_tables.php',
        ] as $path) {
            Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private ?User $adminUser = null;

    protected function adminUser(): User
    {
        if ($this->adminUser) {
            return $this->adminUser;
        }

        foreach (['manage-seo', 'seo-arm-view', 'seo-arm-manage'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $user = User::forceCreate([
            'first_name' => 'SEO',
            'last_name' => 'Admin',
            'mobile' => '09120000001',
            'mobile_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);
        $user->givePermissionTo(['manage-seo', 'seo-arm-view', 'seo-arm-manage']);

        return $this->adminUser = $user;
    }

    protected function plainUser(): User
    {
        return User::forceCreate([
            'first_name' => 'No',
            'last_name' => 'Access',
            'mobile' => '09120000002',
            'mobile_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);
    }

    protected function seedStageOne(): void
    {
        Artisan::call('db:seed', [
            '--class' => \Modules\Seo\Database\Seeders\TamirOnlineSeoStageOneSeeder::class,
            '--force' => true,
        ]);
    }
}
