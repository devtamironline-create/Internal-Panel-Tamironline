<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * فیلدهای لازم برای «مهلت تعیین وضعیت» (SLA) در اپ تکنسین.
 *
 *  - status_changed_at: لحظهٔ ورود به وضعیت فعلی. مبنای اکثر مهلت‌هاست.
 *    تا الان فقط در crm_order_status_logs بود؛ برای فیلتر/مرتب‌سازی روی
 *    لیست سفارش‌ها ستون روی خودِ سفارش لازم است.
 *
 *  - estimated_ready_at: تخمین تکنسین برای آماده‌شدن دستگاه/قطعه. در
 *    وضعیت «انتقال به تعمیرگاه» الزامی و مبنای مهلت همان وضعیت است.
 *
 *  - return_review_*: جریان «تأیید/رد برگشتی». تا وقتی تکنسین تعیین
 *    تکلیف نکرده، اپ شیت اجباری نشان می‌دهد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->timestamp('status_changed_at')->nullable()->after('status');
            $table->date('estimated_ready_at')->nullable()->after('status_changed_at');
            $table->boolean('return_review_pending')->default(false)->after('estimated_ready_at');
            $table->timestamp('return_reviewed_at')->nullable()->after('return_review_pending');
            $table->boolean('return_review_approved')->nullable()->after('return_reviewed_at');
            $table->unsignedTinyInteger('return_review_days')->nullable()->after('return_review_approved');

            $table->index('status_changed_at');
            $table->index('return_review_pending');
        });

        $this->backfillStatusChangedAt();
    }

    public function down(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->dropIndex(['status_changed_at']);
            $table->dropIndex(['return_review_pending']);
            $table->dropColumn([
                'status_changed_at', 'estimated_ready_at', 'return_review_pending',
                'return_reviewed_at', 'return_review_approved', 'return_review_days',
            ]);
        });
    }

    /**
     * پرکردنِ status_changed_at برای سفارش‌های موجود.
     *
     * منبع اول: آخرین ردیفِ تاریخچهٔ وضعیت. اگر سفارشی تاریخچه نداشت،
     * updated_at و در نهایت created_at جایگزین می‌شود تا هیچ ردیفی خالی
     * نماند (خالی‌بودن یعنی مهلت قابل محاسبه نیست).
     */
    private function backfillStatusChangedAt(): void
    {
        if (Schema::hasTable('crm_order_status_logs')) {
            DB::statement('
                UPDATE crm_orders o
                INNER JOIN (
                    SELECT order_id, MAX(created_at) AS last_at
                    FROM crm_order_status_logs
                    GROUP BY order_id
                ) l ON l.order_id = o.id
                SET o.status_changed_at = l.last_at
                WHERE o.status_changed_at IS NULL
            ');
        }

        DB::table('crm_orders')
            ->whereNull('status_changed_at')
            ->update(['status_changed_at' => DB::raw('COALESCE(updated_at, created_at)')]);

        // سفارش‌های برگشتیِ هنوز بررسی‌نشده — تا تکنسین تعیین تکلیف کند.
        if (Schema::hasColumn('crm_orders', 'return_type')) {
            DB::table('crm_orders')
                ->whereNotNull('return_type')
                ->whereNull('return_reviewed_at')
                ->update(['return_review_pending' => true]);
        }
    }
};
