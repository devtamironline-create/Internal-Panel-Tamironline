<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Seo\Services\ContentLinkFixer;

/**
 * دوقلوهای http/https (و اسلشِ انتهایی) در قواعدِ اصلاحِ لینک یک قاعده می‌شوند.
 *
 * قبلاً هش روی متنِ کاملِ URL بود؛ فایلِ برنامه‌نویس برای بعضی مسیرها هر دو
 * scheme را داشت → دو ردیف با یک معنا. موتور هر دو scheme را پیدا می‌کند، پس
 * قاعدهٔ دوم همیشه «۰ مورد» می‌مانْد و گمراه‌کننده بود. این‌جا گروه‌های
 * هم‌معنا merge می‌شوند و هشِ همه به کلیدِ نرمال (بدونِ scheme/اسلش) می‌رود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seo_link_fix_rules')) {
            return;
        }

        try {
            $rows = DB::table('seo_link_fix_rules')->orderBy('id')->get();

            $groups = [];
            foreach ($rows as $row) {
                $groups[ContentLinkFixer::normalizedKey((string) $row->old_url)][] = $row;
            }

            foreach ($groups as $key => $members) {
                // انتخابِ نگه‌دارنده: دارای تغییرِ ثبت‌شده > applied_count بیشتر >
                // اعمال‌شده > دارای مقصد > قدیمی‌ترین.
                usort($members, function ($a, $b) {
                    $chA = DB::table('seo_link_fix_changes')->where('rule_id', $a->id)->exists() ? 1 : 0;
                    $chB = DB::table('seo_link_fix_changes')->where('rule_id', $b->id)->exists() ? 1 : 0;

                    return [$chB, (int) $b->applied_count, $b->status === 'applied' ? 1 : 0, $b->new_url ? 1 : 0, -$b->id]
                        <=> [$chA, (int) $a->applied_count, $a->status === 'applied' ? 1 : 0, $a->new_url ? 1 : 0, -$a->id];
                });

                $keeper = array_shift($members);
                $maxMatches = (int) $keeper->matches_count;

                foreach ($members as $twin) {
                    $maxMatches = max($maxMatches, (int) $twin->matches_count);
                    $hasChanges = DB::table('seo_link_fix_changes')->where('rule_id', $twin->id)->exists();
                    if ($hasChanges) {
                        // تاریخچه دارد — می‌ماند (با هشِ قدیمیِ خودش؛ تداخلی با کلیدِ نرمال ندارد).
                        continue;
                    }
                    DB::table('seo_link_fix_rules')->where('id', $twin->id)->delete();
                }

                DB::table('seo_link_fix_rules')->where('id', $keeper->id)->update([
                    'old_url_hash' => sha1($key),
                    'matches_count' => $maxMatches,
                ]);
            }
        } catch (\Throwable $e) {
            // نباید دیپلوی را بشکند؛ merge در اجرای بعدیِ import هم اتفاق می‌افتد.
        }
    }

    public function down(): void
    {
        // برگشت لازم نیست — دوقلوها عمداً حذف شده‌اند.
    }
};
