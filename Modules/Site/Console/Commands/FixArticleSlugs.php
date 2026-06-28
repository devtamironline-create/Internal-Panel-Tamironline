<?php

namespace Modules\Site\Console\Commands;

use Illuminate\Console\Command;
use Modules\Site\Models\Article;

/**
 * ترمیمِ سرجاییِ slugهای خرابِ مقالات که هنگامِ import از وردپرس مَنگل شده‌اند.
 *
 * علت: post_nameِ فارسیِ وردپرس percent-encoded بود (مثلِ «%d8%a7...») و
 * Str::slug علامتِ «%» را حذف کرد → slugِ هگزی مثلِ
 *   «d8a7d8b1d988d8b1-f23-d985d8a7d8b4db8cd986-...»
 * این کامند آن را به فارسیِ درست برمی‌گرداند:
 *   «ارور-f23-ماشین-لباسشویی-بوش»
 *
 * هر segment (جداشده با «-») که هگزِ زوج‌طول باشد و به UTF-8ِ معتبرِ غیرِ ASCII
 * decode شود، بازگردانده می‌شود؛ بخش‌های لاتین مثلِ «f23» دست‌نخورده می‌مانند.
 *
 * Dry-run by default. برای اعمال --apply بزن.
 *
 *   php artisan blog:fix-slugs
 *   php artisan blog:fix-slugs --apply
 */
class FixArticleSlugs extends Command
{
    protected $signature = 'blog:fix-slugs {--apply : اعمالِ واقعی (پیش‌فرض dry-run)}';

    protected $description = 'ترمیمِ slugهای خرابِ هگزیِ مقالات به فارسیِ درست (read-only تا --apply)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'اعمالِ واقعی' : 'Dry-run (برای اعمال --apply بزن)');

        $fixed = 0;
        $skipped = 0;
        $collisions = 0;

        Article::query()->orderBy('id')->cursor()->each(function (Article $a) use (&$fixed, &$skipped, &$collisions, $apply) {
            $old = (string) $a->slug;
            $new = self::repairSlug($old);
            if ($new === null || $new === $old) {
                return; // مَنگل نبود
            }

            // یکتاسازی در صورتِ برخورد با مقاله‌ی دیگر
            $candidate = $new;
            $i = 2;
            while (Article::where('slug', $candidate)->where('id', '!=', $a->id)->exists()) {
                $candidate = $new.'-'.$i;
                $i++;
                if ($i > 50) {
                    $candidate = $new.'-'.$a->id;
                    break;
                }
            }
            if ($candidate !== $new) {
                $collisions++;
            }

            $this->line("  #{$a->id}: {$old}");
            $this->line("        → {$candidate}");

            if ($apply) {
                $a->slug = $candidate;
                $a->saveQuietly();
            }
            $fixed++;
        });

        $this->newLine();
        $this->info("ترمیم: {$fixed} | برخوردِ یکتاسازی: {$collisions} | بدون‌تغییر: {$skipped}");
        if (! $apply) {
            $this->comment('این dry-run بود؛ چیزی ذخیره نشد. برای اعمال --apply بزن.');
        }

        return self::SUCCESS;
    }

    /**
     * slugِ مَنگل‌شده (هگزِ بدونِ «%») را به فارسی برمی‌گرداند؛ اگر مَنگل نبود null.
     */
    public static function repairSlug(string $slug): ?string
    {
        if ($slug === '') {
            return null;
        }
        $parts = explode('-', $slug);
        $changed = false;

        foreach ($parts as &$seg) {
            if ($seg === '') {
                continue;
            }
            // فقط segmentهایی که هگزِ خالصِ زوج‌طول و حداقل ۲ بایت‌اند (Persian = 2-byte).
            if (! preg_match('/^[0-9a-f]+$/', $seg) || strlen($seg) % 2 !== 0 || strlen($seg) < 4) {
                continue;
            }
            $enc = '%'.implode('%', str_split($seg, 2));
            $dec = rawurldecode($enc);

            // باید UTF-8ِ معتبر، شاملِ حرف، بدونِ کاراکترِ کنترلی، و غیرِ صرفاً ASCII باشد
            // (تا «f23»‌گونه‌ها یا لاتینِ هگزی اشتباه decode نشوند).
            if ($dec !== $seg
                && mb_check_encoding($dec, 'UTF-8')
                && preg_match('/\p{L}/u', $dec)
                && ! preg_match('/[\x00-\x1f\x7f]/', $dec)
                && ! preg_match('/^[\x20-\x7e]+$/', $dec)
            ) {
                $seg = mb_strtolower($dec, 'UTF-8');
                $changed = true;
            }
        }
        unset($seg);

        return $changed ? implode('-', $parts) : null;
    }
}
