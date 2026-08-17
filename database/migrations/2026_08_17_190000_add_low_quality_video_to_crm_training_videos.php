<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نسخهٔ کم‌حجم (کیفیت پایین) ویدیوی آموزشی — اختیاری، برای نتِ ضعیف.
 * تبدیل خودکار (ffmpeg) روی هاست اشتراکی قابل اتکا نیست؛ ادمین خودش
 * نسخهٔ 480p را آپلود می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_training_videos', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_training_videos', 'video_low_url')) {
                $table->string('video_low_url', 500)->nullable()->after('video_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_training_videos', function (Blueprint $table) {
            if (Schema::hasColumn('crm_training_videos', 'video_low_url')) {
                $table->dropColumn('video_low_url');
            }
        });
    }
};
