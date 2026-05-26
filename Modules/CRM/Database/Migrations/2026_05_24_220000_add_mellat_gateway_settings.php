<?php

use Illuminate\Database\Migrations\Migration;
use Modules\CRM\Models\CrmSetting;

/**
 * تنظیمات درگاه دوم (به‌پرداخت ملت) + سوییچ انتخاب درگاه فعال.
 *
 *   payment_gateway      → 'zibal' (پیش‌فرض) | 'mellat'
 *   mellat_terminal_id   → ترمینال‌آی‌دی
 *   mellat_username      → نام کاربری
 *   mellat_password      → رمز عبور
 */
return new class extends Migration
{
    public function up(): void
    {
        // فقط مقداردهی اولیه اگر وجود ندارد (idempotent)
        if (CrmSetting::get('payment_gateway') === null) {
            CrmSetting::set('payment_gateway', 'zibal');
        }
        foreach (['mellat_terminal_id', 'mellat_username', 'mellat_password'] as $key) {
            if (CrmSetting::get($key) === null) {
                CrmSetting::set($key, '');
            }
        }
    }

    public function down(): void
    {
        // تنظیمات را حذف نمی‌کنیم — بی‌خطر برای rollback
    }
};
