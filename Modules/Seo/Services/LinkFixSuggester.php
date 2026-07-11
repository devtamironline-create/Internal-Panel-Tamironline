<?php

namespace Modules\Seo\Services;

use Illuminate\Support\Facades\DB;
use Modules\Seo\Models\SeoLinkTarget;

/**
 * پیشنهادِ نزدیک‌ترین URLِ معتبر برای یک لینکِ خراب — بر پایهٔ شباهتِ مسیر و
 * slug. عمداً «حدسِ قطعی» نمی‌زند: هر پیشنهاد Confidence دارد و پیشنهادهای
 * ضعیف (زیرِ آستانه) اصلاً برنگردانده می‌شوند تا مدیر خودش تصمیم بگیرد.
 */
class LinkFixSuggester
{
    /** حداقلِ اطمینان برای این‌که پیشنهاد اصلاً نمایش داده شود. */
    public const MIN_CONFIDENCE = 40;

    /**
     * @return array{best:?array{url:string,path:string,confidence:int},candidates:list<array{url:string,path:string,confidence:int}>}
     */
    public function suggest(SeoLinkTarget $target): array
    {
        $path = (string) ($target->path ?? $this->pathOf($target->url));
        $empty = ['best' => null, 'candidates' => []];
        if ($path === '' || $path === '/') {
            return $empty;
        }

        // نامزدها: صفحاتِ سالمِ (۲۰۰، قابل‌ایندکس) همین کرال.
        $candidates = DB::table('seo_audits')
            ->where('run_id', $target->run_id)
            ->where('status_code', '>=', 200)
            ->where('status_code', '<', 300)
            ->where('is_noindex', false)
            ->pluck('url')
            ->all();

        if ($candidates === []) {
            return $empty;
        }

        $targetLast = $this->lastSegment($path);
        $targetFirst = $this->firstSegment($path);

        $scored = [];
        $seen = [];
        foreach ($candidates as $url) {
            $candPath = $this->pathOf((string) $url);
            if ($candPath === $path || $candPath === '/' || isset($seen[$candPath])) {
                continue;
            }
            $seen[$candPath] = true;

            $scored[] = [
                'url' => (string) $url,
                'path' => $candPath,
                'confidence' => $this->confidence($path, $candPath, $targetFirst, $targetLast),
            ];
        }

        usort($scored, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);
        $top = array_slice($scored, 0, 3);
        $best = ($top !== [] && $top[0]['confidence'] >= self::MIN_CONFIDENCE) ? $top[0] : null;

        return [
            'best' => $best,
            'candidates' => array_values(array_filter($top, fn ($c) => $c['confidence'] >= self::MIN_CONFIDENCE)),
        ];
    }

    /** امتیازِ ۰..۱۰۰ برای شباهتِ دو مسیر. */
    private function confidence(string $a, string $b, string $aFirst, string $aLast): int
    {
        similar_text($a, $b, $pathPct);
        similar_text($aLast, $this->lastSegment($b), $slugPct);

        // ترکیب: slug مهم‌تر از کلِ مسیر؛ + جایزهٔ هم‌بخش‌بودن (مثلاً هر دو /blog/).
        $score = 0.45 * $pathPct + 0.55 * $slugPct;
        if ($aFirst !== '' && $aFirst === $this->firstSegment($b)) {
            $score = min(100, $score + 12);
        }

        return (int) round($score);
    }

    private function pathOf(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $path = rawurldecode(rtrim((string) $path, '/'));

        return $path === '' ? '/' : $path;
    }

    private function firstSegment(string $path): string
    {
        $s = array_values(array_filter(explode('/', trim($path, '/'))));

        return $s[0] ?? '';
    }

    private function lastSegment(string $path): string
    {
        $s = array_values(array_filter(explode('/', trim($path, '/'))));

        return $s === [] ? '' : end($s);
    }
}
