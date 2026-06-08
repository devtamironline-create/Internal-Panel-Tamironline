<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Site\Http\Controllers\Api\V1\ForumController;

/**
 * بازنویسی slug همه‌ی سوالات موجود به فرمت {id}-{persianSlug}.
 *
 * چرا؟ slugهای قدیمی ممکن است:
 *   - q-{hex}     ← fallback random (بدون SEO)
 *   - some-ascii  ← transliteration ASCII (بدون کلمات فارسی)
 * با این migration همه به فرمت یکپارچه و SEO-friendly تبدیل می‌شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_forum_questions')) {
            return;
        }

        DB::table('site_forum_questions')->orderBy('id')->lazy()->each(function ($row) {
            $newSlug = ForumController::buildQuestionSlug((int) $row->id, (string) $row->title);
            if ($newSlug !== $row->slug) {
                DB::table('site_forum_questions')
                    ->where('id', $row->id)
                    ->update(['slug' => $newSlug]);
            }
        });
    }

    public function down(): void
    {
        // no-op — بازگرداندن slugهای قدیمی غیرعملی است
    }
};
