<?php

use Illuminate\Database\Migrations\Migration;

/**
 * دسترسیِ «باشگاه مشتریان» — داشبوردِ تحلیلِ ورودی‌ها/سفارش‌ها (فقط‌خواندنی).
 * مثلِ بقیهٔ seedها firstOrCreate تا روی پروداکشنِ قبلاً migrate‌شده هم
 * بدونِ دوباره‌کاری کار کند. فقط به نقشِ admin داده می‌شود؛ ادمین‌کل
 * می‌تواند از پنلِ مدیریتِ دسترسی‌ها آن را به بقیه هم بدهد.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $perm = \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => 'view-customer-club', 'guard_name' => 'web']
            );

            \Spatie\Permission\Models\Role::query()->where('name', 'admin')->first()?->givePermissionTo($perm);

            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // جدول‌های permission آماده نیستند (نصبِ تازه) — seeder می‌سازد.
        }
    }

    public function down(): void
    {
        // permission عمداً حذف نمی‌شود.
    }
};
