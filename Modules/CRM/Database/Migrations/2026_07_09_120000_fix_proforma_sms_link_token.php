<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * رفعِ مشکلِ «توکنِ خام به‌جای لینک» در پیامکِ پیش‌فاکتور.
 *
 * قبلاً %token2 = {public_token} بود و انتظار می‌رفت دامنه داخلِ تمپلیتِ
 * تأییدشدهٔ کاوه‌نگار باشد؛ اما تمپلیتِ ثبت‌شده فقط `%token2` داشت، پس مشتری
 * به‌جای لینک، کدِ خام (مثلِ ToXY729...) را می‌دید.
 *
 * حالا %token2 = {receipt_url} (لینکِ کاملِ اپ) می‌شود تا لینکِ کاملِ قابلِ
 * کلیک ارسال شود؛ کاوه‌نگار در Lookup فقط فاصله را محدود می‌کند (که در URL
 * وجود ندارد). دامنه از تنظیمِ proforma_public_url_template می‌آید.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_sms_templates') || ! Schema::hasColumn('crm_sms_templates', 'token_vars')) {
            return;
        }

        DB::table('crm_sms_templates')
            ->where('trigger_key', 'customer_proforma_issued')
            ->update([
                'token_vars' => json_encode([
                    'token' => '{customer_name}',
                    'token2' => '{receipt_url}',   // لینکِ کاملِ اپ به‌جای کدِ خام
                    'token3' => '{amount}',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_sms_templates') || ! Schema::hasColumn('crm_sms_templates', 'token_vars')) {
            return;
        }

        DB::table('crm_sms_templates')
            ->where('trigger_key', 'customer_proforma_issued')
            ->update([
                'token_vars' => json_encode([
                    'token' => '{customer_name}',
                    'token2' => '{public_token}',
                    'token3' => '{amount}',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }
};
