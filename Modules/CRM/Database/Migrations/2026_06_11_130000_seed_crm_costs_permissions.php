<?php

use Illuminate\Database\Migrations\Migration;

/**
 * پرمیشن‌های ماژول هزینه‌ها (view-crm-costs / manage-crm-costs) در
 * PermissionSeeder تعریف شده‌اند ولی روی محیط‌هایی که seeder بعد از
 * افزوده شدنشان اجرا نشده، در DB وجود ندارند — نتیجه: منوی «حسابداری»
 * برای هیچ‌کس (حتی admin) نمایش داده نمی‌شد.
 *
 * این مایگریشن هر دو permission را می‌سازد و به نقش admin و نقش‌هایی
 * که manage-crm-financial دارند (مدیران مالی) می‌دهد.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $view = \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => 'view-crm-costs', 'guard_name' => 'web']
            );
            $manage = \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => 'manage-crm-costs', 'guard_name' => 'web']
            );

            $roles = \Spatie\Permission\Models\Role::query()
                ->where('name', 'admin')
                ->orWhereHas('permissions', fn ($q) => $q->where('name', 'manage-crm-financial'))
                ->get();

            foreach ($roles as $role) {
                $role->givePermissionTo([$view, $manage]);
            }

            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // جدول‌های permission آماده نیستند (نصب تازه) — seeder می‌سازد.
        }
    }

    public function down(): void
    {
        // permission ها عمداً حذف نمی‌شوند.
    }
};
