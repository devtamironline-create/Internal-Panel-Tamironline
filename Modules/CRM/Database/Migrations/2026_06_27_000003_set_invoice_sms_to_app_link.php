<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تبدیلِ پیامکِ «صدور فاکتور» (customer_invoice_issued) به لینکِ امنِ اپ.
 *
 * چرا: لینکِ قبلی شاملِ invoice_code (ترتیبی/قابل‌حدس = IDOR) و دامنهٔ panel بود.
 * حالا توکنِ امنِ غیرقابل‌حدس (public_token) در جای لینک می‌نشیند و دامنه به اپ
 * می‌رود. چون متنِ پیامک و دامنه داخلِ تمپلیتِ تأییدشدهٔ کاوه‌نگار قفل است،
 * باید یک تمپلیتِ جدید در panel.kavenegar.com ثبت شود:
 *
 *   نام پیشنهادی:   CRMInvoiceAppLink
 *   متنِ پیشنهادی:
 *     فاکتور تعمیرآنلاین
 *     %token عزیز، سفارش شما با مبلغ %token2 تومان تکمیل شد.
 *     مشاهده فاکتور:
 *     https://app.tamironline.com/invoice/%token3
 *
 *   token  = نام مشتری
 *   token2 = مبلغ (تومان)
 *   token3 = توکنِ امنِ فاکتور (public_token)
 *
 * اگر هنگامِ ثبت، کاوه‌نگار نامِ دیگری خواست، از «مدیریت پیامک ← ویرایش قالب»
 * می‌توان نام و مَپِ توکن‌ها را بدونِ دیپلوی عوض کرد.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('crm_sms_templates')
            ->where('trigger_key', 'customer_invoice_issued')
            ->update([
                'kavenegar_template' => 'CRMInvoiceAppLink',
                'token_vars' => json_encode([
                    'token' => '{customer_name}',
                    'token2' => '{amount}',
                    'token3' => '{public_token}',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // برگشت به تمپلیتِ قبلیِ پنل (مقادیرِ migrationِ 2026_05_23_120000).
        DB::table('crm_sms_templates')
            ->where('trigger_key', 'customer_invoice_issued')
            ->update([
                'kavenegar_template' => 'CRMOrderSendInvoiceForCustomer',
                'token_vars' => json_encode([
                    'token' => '{customer_name}',
                    'token2' => '{order_code}',
                    'token3' => '{amount}',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }
};
