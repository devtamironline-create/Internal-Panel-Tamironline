<?php

use Illuminate\Database\Migrations\Migration;

/**
 * مجوزِ manage-owner-withdrawals در migrationِ قبلی فقط به نقشِ admin داده شده
 * بود؛ ولی مالک/مدیرِ مالی معمولاً نقشِ سوپر-ادمین (manage-permissions) یا
 * مدیرِ هزینه‌ها (manage-crm-costs) دارد، نه لزوماً نقشِ admin. در نتیجه کارتِ
 * «برداشت سرمایه» در صفحهٔ خروج وجه برایشان دیده نمی‌شد.
 *
 * این migration مجوز را به این نقش‌های مدیریتی هم می‌دهد (idempotent).
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
                ->orWhereHas('permissions', fn ($q) => $q->whereIn('name', ['manage-permissions', 'manage-crm-costs']))
                ->get()
                ->each(fn ($role) => $role->givePermissionTo($perm));

            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // جدول‌های permission آماده نیستند — seeder می‌سازد.
        }
    }

    public function down(): void
    {
        // permission عمداً حذف نمی‌شود.
    }
};
