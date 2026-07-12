<?php

use Illuminate\Database\Migrations\Migration;

/**
 * دسترسیِ مستقلِ «موارد امنیتی» (قفل/مشکوک به تقلبِ سفارش + بلاکِ مشتری).
 * فقط به نقشِ admin (ادمین‌کل) داده می‌شود؛ ادمین‌کل می‌تواند از پنلِ
 * مدیریتِ دسترسی‌ها آن را به نقش‌های دیگر هم بدهد.
 *
 * الگو هم‌ارزِ seed_crm_costs_permissions: firstOrCreate تا روی پروداکشنِ
 * قبلاً migrate‌شده هم بدونِ reseed کار کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $perm = \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => 'manage-order-security', 'guard_name' => 'web']
            );

            $admin = \Spatie\Permission\Models\Role::query()->where('name', 'admin')->first();
            $admin?->givePermissionTo($perm);

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
