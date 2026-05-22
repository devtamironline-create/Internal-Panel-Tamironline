<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kavenegar از پیامک template-based استفاده می‌کند (نه متن آزاد):
 *   POST /v1/{API}/verify/lookup.json
 *   params: template=<name>, token=X, token2=Y, token3=Z, receptor=mobile
 *
 * بنابراین برای هر trigger باید بدانیم:
 *   - نام template در پنل کاوه‌نگار
 *   - برای token/token2/token3 چه متغیری بفرستیم (مثل {customer_name})
 *
 * این migration:
 *   ۱) ستون kavenegar_template + token_vars JSON اضافه می‌کند
 *   ۲) مقادیر پیش‌فرض را برای template های موجود/جدید پر می‌کند
 *      (هم‌سو با template های WP CRM)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_sms_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_sms_templates', 'kavenegar_template')) {
                $table->string('kavenegar_template', 100)->nullable()->after('body');
            }
            if (! Schema::hasColumn('crm_sms_templates', 'token_vars')) {
                $table->json('token_vars')->nullable()->after('kavenegar_template');
            }
        });

        // مقادیر پیش‌فرض همگام با template های WP CRM
        $defaults = [
            'order_created' => [
                'kavenegar_template' => 'customer_order',
                'token_vars' => ['token' => '{customer_name}', 'token2' => '{order_type}', 'token3' => '{subscription}'],
            ],
            'order_assigned' => [
                'kavenegar_template' => 'customer_assigned_tech',
                'token_vars' => ['token' => '{customer_name}', 'token2' => '{technician_name}', 'token3' => '{order_code}'],
            ],
            'order_assigned_tech' => [
                'kavenegar_template' => 'technician_order',
                'token_vars' => ['token' => '{subscription}', 'token2' => '', 'token3' => ''],
            ],
            'order_in_progress' => [
                'kavenegar_template' => 'customer_in_progress',
                'token_vars' => ['token' => '{customer_name}', 'token2' => '{order_code}', 'token3' => ''],
            ],
            'order_completed' => [
                'kavenegar_template' => 'CRMOrderSendInvoiceForCustomer',
                'token_vars' => ['token' => '{customer_name}', 'token2' => '{order_code}', 'token3' => '{amount}'],
            ],
            'order_delivered' => [
                'kavenegar_template' => 'customer_delivered',
                'token_vars' => ['token' => '{customer_name}', 'token2' => '{order_code}', 'token3' => ''],
            ],
            'order_cancelled' => [
                'kavenegar_template' => 'customer_cancel_order',
                'token_vars' => ['token' => '{customer_name}', 'token2' => '{order_code}', 'token3' => ''],
            ],
            'tech_wallet_charged' => [
                'kavenegar_template' => 'tech_wallet_charge',
                'token_vars' => ['token' => '{technician_name}', 'token2' => '{amount}', 'token3' => ''],
            ],
            'tech_payment_received' => [
                'kavenegar_template' => 'tech_payment_received',
                'token_vars' => ['token' => '{technician_name}', 'token2' => '{amount}', 'token3' => ''],
            ],
            'tech_penalty_applied' => [
                'kavenegar_template' => 'tech_penalty',
                'token_vars' => ['token' => '{technician_name}', 'token2' => '{amount}', 'token3' => ''],
            ],
            'tech_order_cancelled' => [
                'kavenegar_template' => 'tech_order_cancelled',
                'token_vars' => ['token' => '{technician_name}', 'token2' => '{order_code}', 'token3' => '{customer_name}'],
            ],
            'tech_invoice_issued' => [
                'kavenegar_template' => 'tech_invoice_issued',
                'token_vars' => ['token' => '{technician_name}', 'token2' => '{order_code}', 'token3' => '{amount}'],
            ],
            'customer_invoice_issued' => [
                'kavenegar_template' => 'CRMOrderSendInvoiceForCustomer',
                'token_vars' => ['token' => '{customer_name}', 'token2' => '{order_code}', 'token3' => '{amount}'],
            ],
            'customer_happy_call' => [
                'kavenegar_template' => 'crmhappycalltemplate',
                'token_vars' => ['token' => '{customer_name}', 'token2' => '{order_code}', 'token3' => ''],
            ],
            'customer_invoice_pay_link' => [
                'kavenegar_template' => 'OrderCustomer',
                'token_vars' => ['token' => '{customer_name}', 'token2' => '{pay_link}', 'token3' => '{order_code}'],
            ],
        ];

        foreach ($defaults as $triggerKey => $data) {
            DB::table('crm_sms_templates')
                ->where('trigger_key', $triggerKey)
                ->whereNull('kavenegar_template')
                ->update([
                    'kavenegar_template' => $data['kavenegar_template'],
                    'token_vars' => json_encode($data['token_vars'], JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('crm_sms_templates', function (Blueprint $table) {
            if (Schema::hasColumn('crm_sms_templates', 'kavenegar_template')) {
                $table->dropColumn('kavenegar_template');
            }
            if (Schema::hasColumn('crm_sms_templates', 'token_vars')) {
                $table->dropColumn('token_vars');
            }
        });
    }
};
