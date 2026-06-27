<?php

use Illuminate\Database\Migrations\Migration;
use Modules\CRM\Models\CrmSetting;

/**
 * سوییچِ لینکِ عمومیِ فاکتور (لینکی که با پیامک به مشتری می‌رود) از رسیدِ پنل
 * به اپ/PWAی مشتری.
 *
 * این مقدار صرفاً تنظیمِ `invoice_public_url_template` را ست می‌کند که
 * Invoice::publicUrl() می‌خواند. هر زمان لازم شد از «تنظیمات → درگاه پرداخت
 * → قالبِ لینکِ عمومیِ فاکتور» قابل تغییر است (خالی = برگشت به رسیدِ پنل).
 */
return new class extends Migration
{
    public function up(): void
    {
        CrmSetting::set('invoice_public_url_template', 'https://app.tamironline.com/invoice/{token}');
    }

    public function down(): void
    {
        // برگشت به پیش‌فرضِ پنل (مقدار خالی).
        CrmSetting::set('invoice_public_url_template', '');
    }
};
