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
            'view-all-tasks' => 'مشاهده تسک همه تیم‌ها',
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
            'warehouse.reprint-invoice' => 'چاپ مجدد فاکتور انبار',

            // تکنسین
            'manage-technicians' => 'مدیریت تکنسین‌ها',
            'approve-technician' => 'تایید/رد درخواست تکنسین',
            'delete-technician' => 'حذف درخواست تکنسین',

            // CRM - داشبورد
            'view-crm-dashboard' => 'مشاهده داشبورد CRM',

            // CRM - سفارش‌ها
            'view-crm-orders' => 'مشاهده سفارش‌های CRM',
            'create-crm-order' => 'ثبت سفارش CRM',
            'edit-crm-order' => 'ویرایش سفارش CRM',
            'delete-crm-order' => 'حذف سفارش CRM',
            'assign-crm-technician' => 'تخصیص تکنسین به سفارش CRM',
            'view-tech-suggestions' => 'مشاهده پیشنهاد هوشمند تکنسین برای سفارش',
            'change-crm-order-status' => 'تغییر وضعیت سفارش CRM',
            'export-crm-orders' => 'خروجی Excel سفارش‌های CRM',

            // CRM - سفارش داخلی + QC
            'view-crm-internal-orders' => 'مشاهده سفارش‌های داخلی CRM',
            'manage-crm-internal-orders' => 'مدیریت سفارش‌های داخلی CRM',
            'qc-approve-internal-order' => 'تایید QC سفارش داخلی',

            // CRM - مشتری‌ها
            'view-crm-customers' => 'مشاهده مشتری‌های CRM',
            'create-crm-customer' => 'افزودن مشتری CRM',
            'edit-crm-customer' => 'ویرایش مشتری CRM',
            'delete-crm-customer' => 'حذف مشتری CRM',

            // CRM - تکنسین‌های فعال
            'view-crm-technicians' => 'مشاهده تکنسین‌های فعال CRM',
            'create-crm-technician' => 'افزودن تکنسین فعال CRM',
            'edit-crm-technician' => 'ویرایش تکنسین فعال CRM',
            'delete-crm-technician' => 'حذف تکنسین فعال CRM',

            // CRM - مالی
            'view-crm-financial' => 'مشاهده مالی CRM',
            'manage-crm-financial' => 'مدیریت مالی CRM',
            'manage-crm-wallet' => 'مدیریت کیف پول تکنسین',
            'view-crm-invoices' => 'مشاهده فاکتورهای CRM',
            'manage-crm-payment-gateway' => 'مدیریت درگاه پرداخت CRM',

            // CRM - گزارش‌ها و ابزارهای مدیریتی
            'view-crm-reports' => 'مشاهده گزارش‌های مدیریتی CRM (مالی، فعالیت روزانه)',
            'manage-crm-orphan-orders' => 'مدیریت سفارش‌های یتیم (تخصیص bulk تکنسین)',
            'manage-crm-legacy-close' => 'بستن retro-active سفارش از لاگ قدیمی (بدون ساخت فاکتور)',

            // CRM - هزینه‌ها
            'view-crm-costs' => 'مشاهده هزینه‌های CRM',
            'manage-crm-costs' => 'مدیریت هزینه‌های CRM',

            // CRM - HappyCall
            'view-crm-happycall' => 'مشاهده پاسخ‌های HappyCall',
            'manage-crm-happycall' => 'مدیریت HappyCall',

            // CRM - پایه (تاکسونومی)
            'view-crm-taxonomies' => 'مشاهده داده‌های پایه CRM',
            'manage-crm-brands' => 'مدیریت برندها',
            'manage-crm-devices' => 'مدیریت دستگاه‌ها',
            'manage-crm-provinces' => 'مدیریت استان‌ها',
            'manage-crm-cities' => 'مدیریت شهرها',

            // CRM - تنظیمات
            'manage-crm-settings' => 'مدیریت تنظیمات CRM',
            'manage-crm-sms-templates' => 'مدیریت قالب‌های SMS سی‌آر‌ام',
            'manage-crm-sync' => 'مدیریت سینک با CRM وردپرسی',

            // CRM - تیکت‌های پشتیبانی تکنسین
            'view-crm-tickets' => 'مشاهده تیکت‌های پشتیبانی تکنسین‌ها',
            'reply-crm-tickets' => 'پاسخ به تیکت‌های پشتیبانی',

            // CRM - پنل تکنسین (نقش جداگانه)
            'view-tech-dashboard' => 'مشاهده داشبورد تکنسین',
            'view-own-orders' => 'مشاهده سفارش‌های خود تکنسین',
            'update-own-order-status' => 'تغییر وضعیت سفارش خود تکنسین',
            'view-own-wallet' => 'مشاهده کیف‌پول خود تکنسین',
            'view-own-invoices' => 'مشاهده فاکتورهای خود تکنسین',

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
                    'view-tasks', 'view-all-tasks', 'create-tasks', 'manage-tasks',
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
            'crm-technician' => [
                'label' => 'تکنسین CRM',
                'permissions' => [
                    'view-tech-dashboard',
                    'view-own-orders',
                    'update-own-order-status',
                    'view-own-wallet',
                    'view-own-invoices',
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
