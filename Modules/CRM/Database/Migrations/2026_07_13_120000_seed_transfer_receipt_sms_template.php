<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ردیفِ تمپلیتِ پیامکِ «رسید انتقال به تعمیرگاه (مشتری)» — تا در صفحهٔ
 * «مدیریت پیامک» دیده و قابلِ ویرایش/فعال‌سازی باشد (مثلِ پیش‌فاکتور).
 *
 * پیش‌فرض غیرفعال است تا در پروداکشن چیزی به‌طورِ ناگهانی تغییر نکند؛ نامِ
 * تمپلیتِ کاوه‌نگار (transferreceipts) پیش‌پر می‌شود چون ادمین آن را ساخته،
 * اما ارسال تا وقتی ادمین قالب را «فعال» نکند از همان مسیرِ متنِ ساده انجام
 * می‌شود (رفتارِ فعلی حفظ می‌گردد).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_sms_templates')) {
            return;
        }
        if (DB::table('crm_sms_templates')->where('trigger_key', 'customer_transfer_receipt')->exists()) {
            return;
        }

        $row = [
            'trigger_key' => 'customer_transfer_receipt',
            'title' => 'رسید انتقال به تعمیرگاه (به مشتری)',
            'recipient' => 'customer',
            'body' => "{customer_name} عزیز، رسید انتقال دستگاه شما ثبت شد. مشاهده:\n{receipt_url}\nتعمیرآنلاین",
            'is_active' => false,
        ];

        // ستون‌های kavenegar_template/token_vars در مایگریشن‌های بعدی اضافه شده‌اند.
        if (Schema::hasColumn('crm_sms_templates', 'kavenegar_template')) {
            $row['kavenegar_template'] = 'transferreceipts';
        }
        if (Schema::hasColumn('crm_sms_templates', 'token_vars')) {
            $row['token_vars'] = json_encode([
                'token' => '{customer_name}',
                'token2' => '{receipt_url}',   // لینکِ کاملِ رسید (نه کدِ خام)
                'token3' => '{order_code}',
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
            DB::table('crm_sms_templates')->where('trigger_key', 'customer_transfer_receipt')->delete();
        }
    }
};
