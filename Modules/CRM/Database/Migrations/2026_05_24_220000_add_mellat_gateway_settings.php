<?php

use Illuminate\Database\Migrations\Migration;
use Modules\CRM\Models\CrmSetting;

/**
 * تنظیمات درگاه دوم «آقای پرداخت» (Aqaye Pardakht) + سوییچ درگاه فعال.
 *
 *   payment_gateway → 'zibal' (پیش‌فرض) | 'aqayepardakht'
 *   aqp_pin         → کد پین درگاه آقای پرداخت
 *   aqp_sandbox     → '1' / '0' (حالت تست با pin=sandbox)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (CrmSetting::get('payment_gateway') === null) {
            CrmSetting::set('payment_gateway', 'zibal');
        }
        if (CrmSetting::get('aqp_pin') === null) {
            CrmSetting::set('aqp_pin', '');
        }
        if (CrmSetting::get('aqp_sandbox') === null) {
            CrmSetting::set('aqp_sandbox', '0');
        }
    }

    public function down(): void
    {
        // تنظیمات حذف نمی‌شوند — بی‌خطر
    }
};
