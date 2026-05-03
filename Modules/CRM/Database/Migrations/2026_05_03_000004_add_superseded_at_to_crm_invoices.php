<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * در WP CRM (libs/order.php → add_status_order)، هر بار وضعیت سفارش به
 * ۵ یا ۱۰ تغییر کند یک financial post جدید ساخته می‌شود — حتی اگر
 * قبلاً ساخته شده. در نتیجه یک سفارش می‌تواند چندین فاکتور تکراری در
 * postmeta WP داشته باشد. وقتی سینک می‌کنیم، هر کدام به یک Invoice
 * مجزا تبدیل می‌شود و باعث «تکرار لاگ فاکتور» در پنل، تورم
 * invoice_debt در محاسبهٔ کیف‌پول، و گزارش‌های اشتباه می‌شود.
 *
 * این migration ستون superseded_at را اضافه می‌کند. فاکتورهای قدیمی
 * هر سفارش (همگی به‌جز جدیدترین) با superseded_at = now() علامت زده
 * می‌شوند. ردیف‌ها حذف نمی‌شوند تا تاریخچه/wp_id حفظ شود؛ یک global
 * scope بعداً آن‌ها را از queryهای پیش‌فرض خارج می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->timestamp('superseded_at')->nullable()->after('paid_at');
            $table->index('superseded_at');
        });

        // backfill: برای هر order_id با بیش از یک فاکتور، همه به‌جز
        // جدیدترین (بزرگ‌ترین id) را superseded می‌کنیم.
        DB::statement('
            UPDATE crm_invoices i
            INNER JOIN (
                SELECT order_id, MAX(id) AS keep_id
                FROM crm_invoices
                GROUP BY order_id
                HAVING COUNT(*) > 1
            ) latest ON latest.order_id = i.order_id
            SET i.superseded_at = NOW()
            WHERE i.id <> latest.keep_id
              AND i.superseded_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('crm_invoices', function (Blueprint $table) {
            $table->dropIndex(['superseded_at']);
            $table->dropColumn('superseded_at');
        });
    }
};
