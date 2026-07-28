<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * بازنویسیِ متنِ دو قالبِ «لغو سفارش» (مشتری و تکنسین).
 *
 * هر دو ردیف از قبل وجود داشتند ولی متنشان حداقلی بود و trigger تکنسین
 * اصلاً صدا زده نمی‌شد. حالا که فراخوانی‌اش وصل شده، متن‌ها هم کامل
 * می‌شوند.
 *
 * is_active عمداً دست نمی‌خورد: تا وقتی الگوهای کاوه‌نگار
 * (customer_cancel_order و tech_order_cancelled) در پنل کاوه‌نگار ثبت و
 * تأیید نشده باشند، فعال‌کردن قالب فقط خطای ارسال تولید می‌کند. مدیر
 * بعد از ثبتِ الگو، از صفحهٔ «مدیریت پیامک» فعالشان می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_sms_templates')) {
            return;
        }

        $rows = [
            'order_cancelled' => [
                'title' => 'لغو سفارش (به مشتری)',
                'recipient' => 'customer',
                'body' => "{customer_name} عزیز\nسفارش {order_code} لغو شد.\nدر صورت نیاز به پیگیری با پشتیبانی تماس بگیرید.\n{shop_name}",
                'kavenegar_template' => 'customer_cancel_order',
                'token_vars' => ['token' => '{customer_name}', 'token2' => '{order_code}', 'token3' => ''],
            ],
            'tech_order_cancelled' => [
                'title' => 'لغو سفارش (به تکنسین)',
                'recipient' => 'technician',
                'body' => "{technician_name} عزیز\nسفارش {order_code} لغو شد و از کارتابل شما حذف می‌شود.\nمشتری: {customer_name}\n{shop_name}",
                'kavenegar_template' => 'tech_order_cancelled',
                'token_vars' => ['token' => '{technician_name}', 'token2' => '{order_code}', 'token3' => '{customer_name}'],
            ],
        ];

        foreach ($rows as $triggerKey => $data) {
            $data['token_vars'] = json_encode($data['token_vars'], JSON_UNESCAPED_UNICODE);
            $data['updated_at'] = now();

            $exists = DB::table('crm_sms_templates')->where('trigger_key', $triggerKey)->exists();

            if ($exists) {
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
    }

    public function down(): void
    {
        // برگرداندنِ متن معنا ندارد.
    }
};
