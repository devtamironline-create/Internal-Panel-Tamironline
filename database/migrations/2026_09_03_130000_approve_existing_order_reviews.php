<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تأییدِ خودکارِ نظرهای موجود (تصمیمِ کارفرما: فعلاً همه تأییدشده باشند).
 *
 * فقط نظرهای «در انتظار» (pending) به «تأییدشده» تبدیل می‌شوند؛ نظرهایی که
 * ادمین صریحاً «رد» کرده دست‌نخورده می‌مانند. افزایشی و بی‌خطر برای production.
 * سپس satisfaction_score تکنسین‌های متأثر بازمحاسبه می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_order_reviews')) {
            return;
        }

        // تکنسین‌هایی که نظرِ pending داشته‌اند (برای بازمحاسبهٔ امتیاز بعد از تأیید).
        $techIds = DB::table('crm_order_reviews')
            ->where('status', 'pending')
            ->whereNotNull('technician_id')
            ->distinct()
            ->pluck('technician_id');

        DB::table('crm_order_reviews')
            ->where('status', 'pending')
            ->update([
                'status' => 'approved',
                'moderated_at' => now(),
            ]);

        // بازمحاسبهٔ میانگینِ رضایت برای تکنسین‌های متأثر.
        if (class_exists(\Modules\CRM\Services\TechnicianRatingService::class)) {
            foreach ($techIds as $techId) {
                try {
                    \Modules\CRM\Services\TechnicianRatingService::recompute((int) $techId);
                } catch (\Throwable $e) {
                    // یک تکنسینِ خراب نباید کلِ مهاجرت را متوقف کند.
                }
            }
        }
    }

    public function down(): void
    {
        // برگشت‌پذیر نیست — نمی‌دانیم کدام‌ها قبلاً pending بوده‌اند.
    }
};
