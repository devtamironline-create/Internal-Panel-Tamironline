<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * حسابداری به یک بخش با دسترسی صریح تبدیل می‌شود:
 *
 * - view-crm-costs و manage-crm-costs از «همهٔ نقش‌ها» (از جمله admin)
 *   پس گرفته می‌شوند تا هیچ‌کس از طریق نقش، خودکار دسترسی نگیرد.
 * - Gate::before هم در AppServiceProvider برای این دو ability استثنا
 *   خورده تا bypass سوپر-ادمین شامل حسابداری نشود.
 * - از این به بعد دسترسی فقط به‌صورت مستقیم به کاربر (از صفحهٔ
 *   مدیریت دسترسی‌ها → ویرایش کاربر → دسترسی‌های مستقیم) داده می‌شود.
 *
 * دسترسی‌های مستقیمی که قبلاً به کاربرها داده شده دست نمی‌خورند.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $permIds = DB::table('permissions')
                ->whereIn('name', ['view-crm-costs', 'manage-crm-costs'])
                ->pluck('id');

            if ($permIds->isNotEmpty()) {
                // فقط اتصال نقش‌ها حذف می‌شود — model_has_permissions
                // (اعطای مستقیم به کاربر) دست‌نخورده می‌ماند.
                DB::table('role_has_permissions')->whereIn('permission_id', $permIds)->delete();
            }

            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // جدول‌های permission آماده نیستند — کاری لازم نیست.
        }
    }

    public function down(): void
    {
        // برگشت عمدی ندارد — اعطای دسترسی از UI انجام می‌شود.
    }
};
