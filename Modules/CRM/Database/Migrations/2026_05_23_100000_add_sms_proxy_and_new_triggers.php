<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * اضافه‌کردن trigger های جدید SMS برای رخدادهایی که قبلاً نبودند:
 *   - tech_wallet_charged، tech_payment_received، tech_penalty_applied
 *   - tech_order_cancelled، tech_invoice_issued
 *   - customer_invoice_issued، customer_happy_call، customer_invoice_pay_link
 *
 * این‌ها به‌صورت پیش‌فرض غیرفعال‌اند تا ادمین تأیید کند. برخی هنوز
 * کد فراخوانی ندارند — در آینده اضافه می‌شوند.
 *
 * + اضافه‌کردن تنظیمات پراکسی پیامک به crm_settings:
 *   sms_proxy_enabled، sms_proxy_url، sms_proxy_secret، kavenegar_api_key
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $newTriggers = [
            [
                'trigger_key' => 'tech_wallet_charged',
                'title' => 'شارژ کیف‌پول (به تکنسین)',
                'recipient' => 'technician',
                'body' => "{technician_name} عزیز\nکیف‌پول شما به مبلغ {amount} تومان شارژ شد.\n{shop_name}",
                'is_active' => false,
            ],
            [
                'trigger_key' => 'tech_payment_received',
                'title' => 'پرداخت/حقوق (به تکنسین)',
                'recipient' => 'technician',
                'body' => "{technician_name} عزیز\nمبلغ {amount} تومان به حساب شما واریز شد.\n{shop_name}",
                'is_active' => false,
            ],
            [
                'trigger_key' => 'tech_penalty_applied',
                'title' => 'جریمه (به تکنسین)',
                'recipient' => 'technician',
                'body' => "{technician_name} عزیز\nجریمه به مبلغ {amount} تومان به حساب شما اعمال شد.\n{shop_name}",
                'is_active' => false,
            ],
            [
                'trigger_key' => 'tech_order_cancelled',
                'title' => 'کنسل سفارش (به تکنسین)',
                'recipient' => 'technician',
                'body' => "{technician_name} عزیز\nسفارش {order_code} (مشتری: {customer_name}) کنسل شد.\n{shop_name}",
                'is_active' => false,
            ],
            [
                'trigger_key' => 'tech_invoice_issued',
                'title' => 'صدور فاکتور (تأیید به تکنسین)',
                'recipient' => 'technician',
                'body' => "{technician_name} عزیز\nفاکتور سفارش {order_code} ثبت شد.\nسهم شما در حال محاسبه است.\n{shop_name}",
                'is_active' => false,
            ],
            [
                'trigger_key' => 'customer_invoice_issued',
                'title' => 'صدور فاکتور (به مشتری)',
                'recipient' => 'customer',
                'body' => "{customer_name} عزیز\nفاکتور سفارش {order_code} به مبلغ {amount} تومان صادر شد.\n{shop_name}",
                'is_active' => false,
            ],
            [
                'trigger_key' => 'customer_happy_call',
                'title' => 'رضایت‌سنجی پس از سفارش (به مشتری)',
                'recipient' => 'customer',
                'body' => "{customer_name} عزیز\nاز اعتماد شما برای سفارش {order_code} ممنونیم. نظرتان را با ما در میان بگذارید.\n{shop_name}",
                'is_active' => false,
            ],
            [
                'trigger_key' => 'customer_invoice_pay_link' ,
                'title' => 'لینک پرداخت فاکتور (به مشتری)',
                'recipient' => 'customer',
                'body' => "{customer_name} عزیز\nمی‌توانید فاکتور سفارش {order_code} را با لینک زیر مشاهده/پرداخت کنید:\n{pay_link}\n{shop_name}",
                'is_active' => false,
            ],
        ];

        foreach ($newTriggers as $row) {
            $exists = DB::table('crm_sms_templates')->where('trigger_key', $row['trigger_key'])->exists();
            if ($exists) continue;
            DB::table('crm_sms_templates')->insert(array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // ── تنظیمات پراکسی پیامک ─────────────────────────────────
        $settings = [
            'kavenegar_api_key'  => '',
            'sms_proxy_enabled'  => '0',
            'sms_proxy_url'      => 'https://api.ganjemarket.com',
            'sms_proxy_secret'   => '',
        ];
        foreach ($settings as $k => $v) {
            DB::table('crm_settings')->updateOrInsert(
                ['key' => $k],
                ['value' => $v, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        $keys = [
            'tech_wallet_charged', 'tech_payment_received', 'tech_penalty_applied',
            'tech_order_cancelled', 'tech_invoice_issued',
            'customer_invoice_issued', 'customer_happy_call', 'customer_invoice_pay_link',
        ];
        DB::table('crm_sms_templates')->whereIn('trigger_key', $keys)->delete();

        $settings = ['kavenegar_api_key', 'sms_proxy_enabled', 'sms_proxy_url', 'sms_proxy_secret'];
        DB::table('crm_settings')->whereIn('key', $settings)->delete();
    }
};
