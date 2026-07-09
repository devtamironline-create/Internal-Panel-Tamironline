<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * متنِ پیامکِ «صدور پیش‌فاکتور» را به شکلِ خواسته‌شده به‌روزرسانی می‌کند و
 * لینک را به دامنهٔ اپ (app.tamironline.com) وصل می‌کند تا مشتری با زدنِ
 * لینک، پیش‌فاکتور را داخلِ اپلیکیشن ببیند.
 *
 * ساختارِ تمپلیتِ تأییدشدهٔ کاوه‌نگار (که ادمین باید بسازد):
 *   پیش فاکتور تعمیرآنلاین
 *   %token عزیز، پیش فاکتور شما در لینک زیر قابل مشاهده است:
 *   app.tamironline.com/proforma/%token2
 *   تعمیرآنلاین
 *
 * بنابراین:
 *   %token  = نام مشتری  ({customer_name})
 *   %token2 = کدِ امنِ داخلِ لینک ({public_token}) — بدونِ کاراکترِ خاص، سازگار با کاوه‌نگار
 *   %token3 = مبلغ ({amount})  (اختیاری؛ اگر تمپلیت داشت)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_sms_templates')) {
            $update = [
                'body' => "{customer_name} عزیز، پیش‌فاکتور شما در لینک زیر قابل مشاهده است:\n{receipt_url}\nتعمیرآنلاین",
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('crm_sms_templates', 'token_vars')) {
                $update['token_vars'] = json_encode([
                    'token' => '{customer_name}',
                    'token2' => '{receipt_url}',   // لینکِ کاملِ اپ (نه کدِ خام)
                    'token3' => '{amount}',
                ], JSON_UNESCAPED_UNICODE);
            }

            // فقط متن/توکن‌ها را عوض می‌کنیم؛ kavenegar_template و is_active را
            // دست نمی‌زنیم تا تنظیماتِ ادمین حفظ شود.
            DB::table('crm_sms_templates')
                ->where('trigger_key', 'customer_proforma_issued')
                ->update($update);
        }

        // لینکِ عمومیِ پیش‌فاکتور به اپ اشاره کند (اگر ادمین قبلاً ست نکرده).
        if (Schema::hasTable('crm_settings')) {
            $exists = DB::table('crm_settings')->where('key', 'proforma_public_url_template')->exists();
            if (! $exists) {
                DB::table('crm_settings')->insert([
                    'key' => 'proforma_public_url_template',
                    'value' => 'https://app.tamironline.com/proforma/{token}',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // برگشت‌ناپذیرِ نرم: متنِ قبلی را برنمی‌گردانیم (بی‌خطر است).
    }
};
