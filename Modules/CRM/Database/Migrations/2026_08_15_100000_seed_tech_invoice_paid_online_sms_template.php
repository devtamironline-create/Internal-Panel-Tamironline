<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * قالبِ پیامکِ «پرداخت آنلاین فاکتور» به تکنسین.
 *
 * وقتی مشتری فاکتور را از درگاه می‌پردازد، کل مبلغ به کیف‌پول تکنسین
 * واریز می‌شود (برآیند با کسرِ سهم شرکتِ هنگام صدور = +سهم تکنسین).
 * این پیامک همان لحظه خبر می‌دهد تا تکنسین سراغ پول نقد نرود.
 *
 * is_active عمداً false می‌ماند: تا الگوی کاوه‌نگار تأیید نشده،
 * فعال‌کردن فقط خطای ارسال تولید می‌کند. مدیر از «مدیریت پیامک» فعالش
 * می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_sms_templates')) {
            return;
        }

        $data = [
            'title' => 'پرداخت آنلاین فاکتور (به تکنسین)',
            'recipient' => 'technician',
            'body' => "{technician_name} عزیز\nفاکتور سفارش {order_code} به مبلغ {amount} تومان توسط مشتری آنلاین پرداخت شد و به کیف‌پول شما اضافه شد.\nنیازی به دریافت وجه نقد از مشتری نیست.",
            'kavenegar_template' => 'techinvoicepaid',
            'token_vars' => json_encode([
                'token' => '{technician_name}', 'token2' => '{order_code}', 'token3' => '{amount}',
            ], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ];

        if (DB::table('crm_sms_templates')->where('trigger_key', 'tech_invoice_paid_online')->exists()) {
            // is_active را دست نمی‌زنیم — تصمیمِ مدیر است.
            DB::table('crm_sms_templates')->where('trigger_key', 'tech_invoice_paid_online')->update($data);

            return;
        }

        DB::table('crm_sms_templates')->insert($data + [
            'trigger_key' => 'tech_invoice_paid_online',
            'is_active' => false,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_sms_templates')) {
            DB::table('crm_sms_templates')->where('trigger_key', 'tech_invoice_paid_online')->delete();
        }
    }
};
