<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * افزودن سه دسترسی جدید برای ابزارهای مدیریتی CRM که اخیراً اضافه
 * شده‌اند. تا کنون از view-crm-financial / manage-crm-financial
 * استفاده می‌کردند که خیلی فراگیر بود. حالا granular می‌شوند.
 *
 * idempotent: firstOrCreate جلوی duplicate را می‌گیرد.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'view-crm-reports'           => 'مشاهده گزارش‌های مدیریتی CRM (مالی، فعالیت روزانه)',
            'manage-crm-orphan-orders'   => 'مدیریت سفارش‌های یتیم (تخصیص bulk تکنسین)',
            'manage-crm-legacy-close'    => 'بستن retro-active سفارش از لاگ قدیمی (بدون ساخت فاکتور)',
        ];

        foreach ($permissions as $name => $_desc) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['name' => $name, 'guard_name' => 'web']
            );
        }

        // به نقش admin همه را اضافه کن (تا super-admin همچنان همه چیز ببیند)
        $admin = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo(array_keys($permissions));
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'view-crm-reports',
            'manage-crm-orphan-orders',
            'manage-crm-legacy-close',
        ])->delete();
    }
};
