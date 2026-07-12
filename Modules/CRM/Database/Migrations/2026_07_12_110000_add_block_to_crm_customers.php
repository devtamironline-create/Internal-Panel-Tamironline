<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بلاکِ مشتری (سطحِ حسابِ کاربری، نه سفارش). مشتریِ بلاک‌شده نمی‌تواند سفارشِ
 * جدید ثبت کند، اما سوابق و سفارش‌های قبلی‌اش کاملاً حفظ می‌شوند.
 *
 * ستون‌ها افزایشی و nullable/با پیش‌فرضِ امن‌اند؛ هیچ دادهٔ موجودی تغییر نمی‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_customers', function (Blueprint $table) {
            $table->boolean('is_blocked')->default(false)->index();
            $table->string('block_reason', 500)->nullable();
            $table->unsignedBigInteger('blocked_by')->nullable();
            $table->timestamp('blocked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crm_customers', function (Blueprint $table) {
            $table->dropColumn(['is_blocked', 'block_reason', 'blocked_by', 'blocked_at']);
        });
    }
};
