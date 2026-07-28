<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * هم‌ترازکردنِ نامِ الگوهای کاوه‌نگارِ «لغو سفارش» با آنچه واقعاً در پنل
 * کاوه‌نگار ساخته شده.
 *
 * نامِ الگو در کاوه‌نگار باید مو‌به‌مو با مقدارِ ذخیره‌شده یکی باشد، وگرنه
 * درخواستِ verify/lookup با خطای «الگو یافت نشد» برمی‌گردد. الگوها بدون
 * زیرخط ساخته شده‌اند، پس همان را ثبت می‌کنیم.
 *
 * فقط ستون kavenegar_template به‌روز می‌شود — متن، گیرنده، توکن‌ها و
 * is_active دست نمی‌خورند.
 */
return new class extends Migration
{
    private const NAMES = [
        'order_cancelled' => 'customercancelorder',
        'tech_order_cancelled' => 'techordercancelled',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('crm_sms_templates')) {
            return;
        }

        foreach (self::NAMES as $triggerKey => $templateName) {
            DB::table('crm_sms_templates')
                ->where('trigger_key', $triggerKey)
                ->update([
                    'kavenegar_template' => $templateName,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_sms_templates')) {
            return;
        }

        foreach (['order_cancelled' => 'customer_cancel_order', 'tech_order_cancelled' => 'tech_order_cancelled'] as $triggerKey => $old) {
            DB::table('crm_sms_templates')
                ->where('trigger_key', $triggerKey)
                ->update(['kavenegar_template' => $old, 'updated_at' => now()]);
        }
    }
};
