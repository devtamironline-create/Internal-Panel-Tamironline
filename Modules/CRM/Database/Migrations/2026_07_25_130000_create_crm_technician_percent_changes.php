<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تغییراتِ زمان‌دارِ درصدِ کمیسیونِ تکنسین.
 *
 * هر ردیف می‌گوید «از تاریخ/ساعتِ effective_from، درصدِ کمیسیون (و/یا درصدِ دوم)
 * این مقدار است». محاسباتِ مالی (CommissionCalculator) درصدِ مؤثر در لحظهٔ
 * تکمیلِ سفارش را از همین تاریخچه می‌خوانند — پس تغییرِ درصد، تاریخِ مالیِ
 * گذشتهٔ تکنسین را بازنویسی نمی‌کند. ستون‌های فعلیِ percent/tech_per_of_all روی
 * خودِ تکنسین به‌عنوانِ مقدارِ پایه (قبل از اولین تغییرِ زمان‌دار) می‌مانند.
 *
 * نامِ ایندکس صریح و کوتاه است — نامِ خودکارِ لاراول از سقفِ ۶۴ کاراکترِ MySQL
 * رد می‌شد (خطای 1059). چون اجرای اولِ این migration روی پروداکشن بعد از ساختِ
 * جدول و قبل از ایندکس FAIL شد، up() حالتِ نیمه‌اجراشده را هم ترمیم می‌کند.
 */
return new class extends Migration
{
    private const INDEX = 'tpc_tech_effective_idx';

    public function up(): void
    {
        if (! Schema::hasTable('crm_technician_percent_changes')) {
            Schema::create('crm_technician_percent_changes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('technician_id')->constrained('crm_technicians')->cascadeOnDelete();
                // null = «این فیلد تغییر نمی‌کند» (فقط فیلدِ دیگر عوض می‌شود)
                $table->unsignedTinyInteger('percent')->nullable();          // درصد کمیسیون (External)
                $table->unsignedTinyInteger('tech_per_of_all')->nullable();  // درصد دوم (Internal)
                $table->dateTime('effective_from');                          // از این لحظه معتبر است
                $table->string('note', 500)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['technician_id', 'effective_from'], self::INDEX);
            });

            return;
        }

        // جدول از اجرای ناقصِ قبلی مانده (ایندکس شکست خورده بود) → فقط ایندکس.
        if (! Schema::hasIndex('crm_technician_percent_changes', self::INDEX)) {
            Schema::table('crm_technician_percent_changes', function (Blueprint $table) {
                $table->index(['technician_id', 'effective_from'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_technician_percent_changes');
    }
};
