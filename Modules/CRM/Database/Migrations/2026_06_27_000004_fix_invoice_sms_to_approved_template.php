<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * اصلاحِ مایگریشنِ 000003: استفاده از همان تمپلیتِ *تأییدشدهٔ* کاوه‌نگار
 * (`CRMOrderSendInvoiceForCustomer`) ولی با توکنِ امن در جای لینک.
 *
 * ساختارِ تمپلیتِ تأییدشده:
 *   %token  = نام مشتری
 *   %token2 = کدِ داخلِ لینک →  https://panel.tamironline.com/crm/receipt/%token2
 *   %token3 = مبلغ
 *
 * بنابراین %token2 را به {public_token} (امنِ غیرقابل‌حدس) مَپ می‌کنیم تا
 * لینکِ پیامک هم امن شود و هم بلافاصله — بدونِ نیاز به تأییدِ تمپلیتِ جدید —
 * کار کند (مسیرِ /crm/receipt/{token} با public_token resolve می‌شود).
 *
 * سوییچ به دامنهٔ اپ (app.tamironline.com) قدمِ بعدی است: یک تمپلیتِ جدید
 * با URLِ اپ در کاوه‌نگار ثبت کنید و سپس فقط «نام تمپلیت» را در
 * «مدیریت پیامک ← ویرایش قالب» عوض کنید.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('crm_sms_templates')
            ->where('trigger_key', 'customer_invoice_issued')
            ->update([
                'kavenegar_template' => 'CRMOrderSendInvoiceForCustomer',
                'token_vars' => json_encode([
                    'token' => '{customer_name}',
                    'token2' => '{public_token}',
                    'token3' => '{amount}',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // برگشت به وضعیتِ مایگریشنِ 000003 (تمپلیتِ اپ که هنوز تأیید نشده).
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
};
