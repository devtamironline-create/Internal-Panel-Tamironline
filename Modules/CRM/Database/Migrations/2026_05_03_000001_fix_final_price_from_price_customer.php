<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * اصلاح داده‌های موجود: تا قبل از این، sync سفارش‌ها از WP، فیلد
 * final_price را از total_invoice (مانده پس از کسر هزینه‌ها) پر می‌کرد
 * که منجر به نمایش اشتباه «مبلغ نهایی» در پنل می‌شد. حالا باید مبلغ
 * را از price_customer (جمع کل صورت حساب) بگیریم.
 *
 * این migration تنها سفارش‌هایی را تصحیح می‌کند که:
 *   - price_customer > 0 دارند (داده WP)
 *   - و final_price فعلی برابر total_invoice است (یعنی توسط sync قدیم
 *     ست شده، نه دستی توسط ادمین)
 *
 * هم‌چنین مبلغ فاکتور (crm_invoices.total_amount) که از همان منطق
 * استفاده می‌کرد به price_customer منتقل می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) crm_orders.final_price ← price_customer
        DB::table('crm_orders')
            ->whereNotNull('price_customer')
            ->where('price_customer', '>', 0)
            ->whereColumn('final_price', 'total_invoice')
            ->update(['final_price' => DB::raw('price_customer')]);

        // 2) crm_invoices.total_amount ← orders.price_customer
        // فقط فاکتورهایی که order مرتبطشان price_customer دارد و
        // total_amount فعلی آن‌ها از total_invoice (مانده) خوانده شده.
        DB::statement('
            UPDATE crm_invoices i
            INNER JOIN crm_orders o ON o.id = i.order_id
            SET i.total_amount = o.price_customer
            WHERE o.price_customer IS NOT NULL
              AND o.price_customer > 0
              AND i.total_amount = o.total_invoice
        ');
    }

    public function down(): void
    {
        // داده‌محافظ: backfill برگشت‌پذیر نیست چون از کجا می‌دانستیم
        // قبلاً برابر total_invoice بود؟ rollback عمداً no-op است.
    }
};
