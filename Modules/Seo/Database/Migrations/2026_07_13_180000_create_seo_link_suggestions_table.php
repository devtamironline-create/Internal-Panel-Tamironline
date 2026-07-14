<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * پیشنهادهای لینک‌سازیِ داخلی — هر ردیف: «در صفحهٔ X، متنِ anchor را به مقصدِ Y
 * لینک کن». جریان: تولید (pending) → تأیید/رد ادمین → اعمال (با snapshotِ قبلی
 * برای بازگردانی).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seo_link_suggestions')) {
            return;
        }

        Schema::create('seo_link_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 20);            // article (فعلاً فقط مقاله قابلِ اعمالِ خودکار)
            $table->unsignedBigInteger('entity_id');
            $table->string('page_path', 500);             // مسیرِ صفحهٔ مبدأ
            $table->string('page_label', 300)->nullable();
            $table->string('target_path', 500);           // مقصدِ لینک
            $table->string('anchor', 300);                // متنِ انکر (باید در محتوا موجود باشد)
            $table->string('reason', 300)->nullable();    // دلیلِ پیشنهاد
            $table->string('island', 100)->nullable();    // جزیره (slug دستگاه)
            $table->string('priority', 10)->default('medium'); // high|medium|low
            $table->string('status', 20)->default('pending');  // pending|approved|rejected|applied
            $table->longText('applied_before')->nullable();     // snapshotِ فیلد قبل از اعمال
            $table->string('applied_field', 60)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id', 'target_path'], 'uniq_page_target');
            $table->index(['status', 'priority']);
            $table->index('island');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_link_suggestions');
    }
};
