<?php

use Illuminate\Database\Migrations\Migration;

/**
 * دسترسیِ مستقلِ «مدیریت رسید انتقال» — دیدن، ویرایش، حذف و ارسالِ مجددِ
 * پیامکِ رسیدهای انتقال از پنلِ ادمین. فقط به نقشِ admin (ادمین‌کل) داده
 * می‌شود؛ ادمین‌کل می‌تواند از صفحهٔ مدیریتِ دسترسی‌ها آن را به هر شخص/نقشِ
 * دیگری هم بدهد.
 *
 * الگو هم‌ارزِ seed_order_security_permission: firstOrCreate تا روی پروداکشنِ
 * قبلاً migrate‌شده هم بدونِ reseed کار کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $perm = \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => 'manage-transfer-receipts', 'guard_name' => 'web']
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
