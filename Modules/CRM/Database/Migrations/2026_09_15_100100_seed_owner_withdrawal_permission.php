<?php

use Illuminate\Database\Migrations\Migration;

/**
 * مجوزِ «برداشت سرمایه (مالک/شرکا)» — یک مجوزِ واحدِ مدیریتی برای مشاهده/ثبت/
 * ویرایش/حذفِ برداشت‌ها. این اطلاعاتِ مالیِ مدیریتی است؛ کاربرانِ عادیِ پنل
 * نباید الزاماً دسترسی داشته باشند. به نقشِ admin داده می‌شود (admin از طریقِ
 * Gate::before هم مجاز است؛ این grant برای assignشدنیِ صریح است).
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $perm = \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => 'manage-owner-withdrawals', 'guard_name' => 'web']
            );

            \Spatie\Permission\Models\Role::query()
                ->where('name', 'admin')
                ->get()
                ->each(fn ($role) => $role->givePermissionTo($perm));

            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // جدول‌های permission آماده نیستند (نصب تازه) — seeder می‌سازد.
        }
    }

    public function down(): void
    {
        // permission عمداً حذف نمی‌شود.
    }
};
