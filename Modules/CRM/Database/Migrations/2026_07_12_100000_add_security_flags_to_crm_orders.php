<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * پرچم‌های امنیتیِ سفارش (مستقل از وضعیت):
 *   - قفل شده توسط ادمین: ویرایش/تغییرِ وضعیت مسدود می‌شود تا باز شود.
 *   - مشکوک به تقلب: صرفاً علامت‌گذاری برای بررسی.
 *
 * همه‌ی ستون‌ها افزایشی و nullable/با پیش‌فرضِ امن‌اند؛ هیچ دادهٔ موجودی
 * تغییر نمی‌کند. بدونِ FK constraint (روی جدولِ بزرگِ سفارش‌ها، constraint
 * می‌تواند قفل/کندی ایجاد کند؛ این‌ها فیلدهای auditاند).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->index();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_reason', 500)->nullable();

            $table->boolean('is_suspected_fraud')->default(false)->index();
            $table->unsignedBigInteger('fraud_flagged_by')->nullable();
            $table->timestamp('fraud_flagged_at')->nullable();
            $table->string('fraud_note', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crm_orders', function (Blueprint $table) {
            $table->dropColumn([
                'is_locked', 'locked_by', 'locked_at', 'lock_reason',
                'is_suspected_fraud', 'fraud_flagged_by', 'fraud_flagged_at', 'fraud_note',
            ]);
        });
    }
};
