<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * قالب‌های پیامکِ «اعلانِ اپِ تکنسین».
 *
 * اپِ تکنسین در ایران پوشِ آنی ندارد و همگام‌سازیِ پس‌زمینه تا نیم ساعت
 * تأخیر دارد. سه رویدادِ زیر نمی‌توانند منتظر بمانند، پس از کانالِ پیامک
 * هم می‌روند.
 *
 * is_active عمداً false می‌ماند: تا وقتی الگوهای کاوه‌نگار ثبت و تأیید
 * نشده باشند، فعال‌کردن قالب فقط خطای ارسال تولید می‌کند. مدیر بعد از
 * تأییدِ الگو از صفحهٔ «مدیریت پیامک» فعالشان می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_sms_templates')) {
            return;
        }

        $rows = [
            'tech_sla_warning' => [
                'title' => 'یادآور مهلت تعیین وضعیت (به تکنسین)',
                'recipient' => 'technician',
                'body' => "{technician_name} عزیز\nیک ساعت تا پایان مهلت ثبت وضعیت سفارش {order_code} باقی است.\nلطفاً در اپ تعمیرآنلاین وضعیت را ثبت کنید.",
                'kavenegar_template' => 'techslawarning',
                'token_vars' => ['token' => '{technician_name}', 'token2' => '{order_code}', 'token3' => ''],
            ],
            'tech_sla_due' => [
                'title' => 'رسیدن مهلت تعیین وضعیت (به تکنسین)',
                'recipient' => 'technician',
                'body' => "{technician_name} عزیز\nمهلت ثبت وضعیت سفارش {order_code} رسید.\nتا ثبت نشدن وضعیت، امکان ادامهٔ کار در اپ نیست.",
                'kavenegar_template' => 'techsladue',
                'token_vars' => ['token' => '{technician_name}', 'token2' => '{order_code}', 'token3' => ''],
            ],
            'tech_order_returned' => [
                'title' => 'برگشت خوردن سفارش (به تکنسین)',
                'recipient' => 'technician',
                'body' => "{technician_name} عزیز\nسفارش {order_code} برگشت خورده و منتظر تأیید یا رد شماست.\nلطفاً در اپ تعمیرآنلاین بررسی کنید.",
                'kavenegar_template' => 'techorderreturned',
                'token_vars' => ['token' => '{technician_name}', 'token2' => '{order_code}', 'token3' => ''],
            ],
        ];

        foreach ($rows as $triggerKey => $data) {
            $data['token_vars'] = json_encode($data['token_vars'], JSON_UNESCAPED_UNICODE);
            $data['updated_at'] = now();

            if (DB::table('crm_sms_templates')->where('trigger_key', $triggerKey)->exists()) {
                // is_active را دست نمی‌زنیم — تصمیمِ مدیر است.
                DB::table('crm_sms_templates')->where('trigger_key', $triggerKey)->update($data);

                continue;
            }

            DB::table('crm_sms_templates')->insert($data + [
                'trigger_key' => $triggerKey,
                'is_active' => false,
                'created_at' => now(),
            ]);
        }

        // پنجرهٔ پیش‌فرضِ ساعتِ مجاز — فقط اگر قبلاً تنظیم نشده باشد.
        if (Schema::hasTable('crm_settings')) {
            foreach (['tech_sms_quiet_start' => '8', 'tech_sms_quiet_end' => '22'] as $key => $value) {
                if (DB::table('crm_settings')->where('key', $key)->exists()) {
                    continue;
                }

                DB::table('crm_settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_sms_templates')) {
            DB::table('crm_sms_templates')
                ->whereIn('trigger_key', ['tech_sla_warning', 'tech_sla_due', 'tech_order_returned'])
                ->delete();
        }
    }
};
