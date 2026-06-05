<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول آرشیو تراکنش‌های کیف‌پول — فقط برای نمایش تاریخی.
 *
 * این جدول هرگز در هیچ محاسبه‌ای استفاده نمی‌شود:
 *   - wallet_balance ✗
 *   - true_balance ✗
 *   - invoice_debt ✗
 *   - گزارش مالی ✗
 *   - SyncFinancial ✗
 *
 * فقط در WalletArchiveService خوانده می‌شود (که خود فقط در
 * WalletController::show و در یک تب جداگانهٔ UI نمایش می‌دهد).
 *
 * منبع داده: WP `or_posts WHERE post_type='financial'` که توسط کامند
 * crm:wallet:import-archive-from-wp وارد می‌شود (idempotent).
 *
 * هیچ FK تعریف نمی‌شود — این جدول کاملاً مستقل است از
 * crm_tech_wallet_transactions و حذف تکنسین/سفارش هیچ تأثیری ندارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_wallet_archive_txs', function (Blueprint $table) {
            $table->id();

            // مرجع WP — unique تا import idempotent باشد
            $table->unsignedBigInteger('wp_post_id');
            $table->unique('wp_post_id', 'crm_wallet_archive_wp_post_unique');

            // ارجاع‌های soft (بدون FK) — برای فیلتر در UI
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->unsignedBigInteger('technician_wp_id')->nullable();
            $table->unsignedBigInteger('order_wp_id')->nullable();

            // داده‌ی اصلی
            $table->string('type', 30);   // wallet_charge, reward, penalty, etc.
            $table->bigInteger('amount'); // signed
            $table->string('note', 1000)->nullable();
            $table->string('refid', 100)->nullable();
            $table->unsignedTinyInteger('payment_status')->default(0);

            // زمان‌ها
            $table->timestamp('wp_created_at')->nullable();
            $table->timestamp('imported_at')->useCurrent();

            // ایندکس‌ها
            $table->index('technician_id', 'crm_wallet_archive_tech_idx');
            $table->index('technician_wp_id', 'crm_wallet_archive_tech_wp_idx');
            $table->index('order_wp_id', 'crm_wallet_archive_order_wp_idx');
            $table->index(['technician_id', 'wp_created_at'], 'crm_wallet_archive_tech_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_wallet_archive_txs');
    }
};
