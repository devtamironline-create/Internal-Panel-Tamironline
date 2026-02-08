<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            // پرسنل
            'view-staff' => 'مشاهده پرسنل',
            'manage-staff' => 'مدیریت پرسنل',

            // حضور و غیاب
            'view-attendance' => 'مشاهده حضور و غیاب خود',
            'manage-attendance' => 'مدیریت حضور و غیاب',

            // مرخصی
            'view-leave' => 'مشاهده مرخصی خود',
            'request-leave' => 'درخواست مرخصی',
            'manage-leave' => 'مدیریت مرخصی (تایید/رد)',

            // تسک
            'view-tasks' => 'مشاهده تسک‌ها',
            'create-tasks' => 'ایجاد تسک',
            'manage-tasks' => 'مدیریت تسک‌ها',

            // تیم
            'view-teams' => 'مشاهده تیم‌ها',
            'manage-teams' => 'مدیریت تیم‌ها',

            // گزارش
            'view-reports' => 'مشاهده گزارش‌ها',

            // OKR
            'view-okr' => 'مشاهده OKR',
            'manage-okr' => 'مدیریت OKR',

            // حقوق
            'view-salary' => 'مشاهده حقوق خود',
            'manage-salary' => 'مدیریت حقوق',

            // پیام‌رسان
            'use-messenger' => 'استفاده از پیام‌رسان',

            // انبار
            'view-warehouse' => 'مشاهده انبار',
            'manage-warehouse' => 'مدیریت انبار',

            // تنظیمات
            'manage-settings' => 'مدیریت تنظیمات',
            'manage-permissions' => 'مدیریت دسترسی‌ها',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['name' => $name, 'guard_name' => 'web']
            );
        }

        // Create Roles
        $roles = [
            'admin' => [
                'label' => 'مدیر سیستم',
                'permissions' => array_keys($permissions), // All permissions
            ],
            'manager' => [
                'label' => 'مدیر',
                'permissions' => [
                    'view-staff', 'manage-staff',
                    'view-attendance', 'manage-attendance',
                    'view-leave', 'request-leave', 'manage-leave',
                    'view-tasks', 'create-tasks', 'manage-tasks',
                    'view-teams', 'manage-teams',
                    'view-reports',
                    'use-messenger',
                ],
            ],
            'supervisor' => [
                'label' => 'سرپرست',
                'permissions' => [
                    'view-staff',
                    'view-attendance', 'manage-attendance',
                    'view-leave', 'request-leave', 'manage-leave',
                    'view-tasks', 'create-tasks', 'manage-tasks',
                    'view-teams',
                    'view-reports',
                    'use-messenger',
                ],
            ],
            'staff' => [
                'label' => 'کارمند',
                'permissions' => [
                    'view-attendance',
                    'view-leave', 'request-leave',
                    'view-tasks', 'create-tasks',
                    'view-teams',
                    'use-messenger',
                ],
            ],
        ];

        foreach ($roles as $name => $data) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['name' => $name, 'guard_name' => 'web']
            );
            $role->syncPermissions($data['permissions']);
        }

        // Assign admin role to first user if exists
        $firstUser = \App\Models\User::where('is_staff', true)->first();
        if ($firstUser && !$firstUser->hasRole('admin')) {
            $firstUser->assignRole('admin');
        }
    }
}
