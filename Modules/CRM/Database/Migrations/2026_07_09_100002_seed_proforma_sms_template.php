<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ردیفِ تمپلیتِ پیامکِ «صدور پیش‌فاکتور (مشتری)» — تا در «مدیریت پیامک»
 * قابلِ ویرایش/فعال‌سازی باشد. پیش‌فرض غیرفعال (ادمین باید یک تمپلیتِ
 * تأییدشدهٔ کاوه‌نگار وصل و فعال کند).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_sms_templates')) {
            return;
        }
        if (DB::table('crm_sms_templates')->where('trigger_key', 'customer_proforma_issued')->exists()) {
            return;
        }

        $row = [
            'trigger_key' => 'customer_proforma_issued',
            'title' => 'صدور پیش‌فاکتور (به مشتری)',
            'recipient' => 'customer',
            'body' => "{customer_name} عزیز، پیش‌فاکتور شما در لینک زیر قابل مشاهده است:\n{receipt_url}\nتعمیرآنلاین",
            'is_active' => false,
        ];

        // ستون‌های kavenegar_template/token_vars در مایگریشن‌های بعدی اضافه شده‌اند.
        if (Schema::hasColumn('crm_sms_templates', 'kavenegar_template')) {
            $row['kavenegar_template'] = '';
        }
        if (Schema::hasColumn('crm_sms_templates', 'token_vars')) {
            $row['token_vars'] = json_encode([
                'token' => '{customer_name}',
                'token2' => '{receipt_url}',   // لینکِ کاملِ اپ (نه کدِ خام)
                'token3' => '{amount}',
            ], JSON_UNESCAPED_UNICODE);
        }

        DB::table('crm_sms_templates')->insert(array_merge($row, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_sms_templates')) {
            DB::table('crm_sms_templates')->where('trigger_key', 'customer_proforma_issued')->delete();
        }
    }
};
