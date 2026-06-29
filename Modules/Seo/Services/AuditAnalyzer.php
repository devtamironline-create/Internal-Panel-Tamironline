<?php

namespace Modules\Seo\Services;

use Modules\Seo\Models\SeoAudit;
use Modules\Seo\Models\SeoAuditRun;
use Modules\Seo\Models\SeoSetting;

/**
 * تبدیل سیگنال‌های خام آدیت به فهرست مشکلات با شدت + امتیاز کلی.
 * pure و واحد-تست‌پذیر.
 */
class AuditAnalyzer
{
    private const RANK = ['good' => 0, 'notice' => 1, 'warning' => 2, 'critical' => 3];

    private const PENALTY = ['notice' => 5, 'warning' => 15, 'critical' => 40];

    /** کدهای مشکل canonical که در پاس پس از run محاسبه می‌شوند (cross-row). */
    public const CANONICAL_CODES = [
        'canonical_empty',
        'canonical_to_redirect',
        'canonical_to_noindex',
        'canonical_to_404',
        'canonical_offsite',
    ];

    public function __construct(
        private readonly ?RedirectMatcher $redirects = null,
    ) {}

    /**
     * @param  array<string, mixed>  $a  خروجی OnPageAuditor::audit
     * @return array{issues:list<array{code:string,severity:string,message:string}>,severity:string,score:int}
     */
    public function analyze(array $a): array
    {
        $issues = [];
        $add = function (string $code, string $severity, string $message) use (&$issues) {
            $issues[] = compact('code', 'severity', 'message');
        };

        $status = (int) ($a['status_code'] ?? 0);
        if ($status === 0 || $status >= 500) {
            $add('http_error', 'critical', 'صفحه در دسترس نیست یا خطای سرور دارد ('.$status.').');
        } elseif ($status === 404 || $status === 410) {
            $add('not_found', 'critical', 'صفحه یافت نشد ('.$status.').');
        } elseif ($status >= 300 && $status < 400) {
            $add('redirect', 'notice', 'صفحه ریدایرکت می‌شود ('.$status.').');
        }

        $redirects = (int) ($a['redirect_count'] ?? 0);
        if ($redirects > 3) {
            $add('redirect_chain', 'warning', 'زنجیرهٔ ریدایرکت طولانی ('.$redirects.' پرش).');
        }

        // فقط برای صفحاتی که واقعاً لود شده‌اند، محتوای متا را بررسی کن.
        if ($status >= 200 && $status < 300) {
            $titleLen = (int) ($a['title_length'] ?? 0);
            if ($titleLen === 0) {
                $add('title_missing', 'critical', 'صفحه عنوان (title) ندارد.');
            } elseif ($titleLen > 60) {
                $add('title_long', 'notice', 'عنوان بلندتر از ۶۰ نویسه است ('.$titleLen.').');
            } elseif ($titleLen < 15) {
                $add('title_short', 'notice', 'عنوان کوتاه است ('.$titleLen.').');
            }

            $descLen = (int) ($a['description_length'] ?? 0);
            if ($descLen === 0) {
                $add('description_missing', 'warning', 'توضیحات متا (meta description) ندارد.');
            } elseif ($descLen > 160) {
                $add('description_long', 'notice', 'توضیحات متا بلندتر از ۱۶۰ نویسه است ('.$descLen.').');
            }

            $h1 = (int) ($a['h1_count'] ?? 0);
            if ($h1 === 0) {
                $add('h1_missing', 'warning', 'صفحه هیچ H1 ندارد.');
            } elseif ($h1 > 1) {
                $add('h1_multiple', 'notice', 'بیش از یک H1 وجود دارد ('.$h1.').');
            }

            if (! empty($a['is_noindex'])) {
                $add('noindex', 'warning', 'صفحه با noindex از ایندکس خارج شده است.');
            }
            if (empty($a['canonical'])) {
                $add('canonical_missing', 'notice', 'تگ canonical ندارد.');
            }
            if (empty($a['jsonld_present'])) {
                $add('schema_missing', 'notice', 'داده‌ساختاریافته (JSON-LD) ندارد.');
            }
            if (empty($a['og_present'])) {
                $add('og_missing', 'notice', 'تگ‌های Open Graph ندارد.');
            }

            $noAlt = (int) ($a['images_without_alt'] ?? 0);
            if ($noAlt > 0) {
                $add('images_alt', $noAlt > 5 ? 'warning' : 'notice', $noAlt.' تصویر بدون alt دارد.');
            }
            if ((int) ($a['internal_links'] ?? 0) === 0) {
                $add('no_internal_links', 'notice', 'هیچ لینک داخلی ندارد.');
            }
            if ((int) ($a['word_count'] ?? 0) < 300) {
                $add('thin_content', 'notice', 'محتوای کم (کمتر از ۳۰۰ کلمه).');
            }
            if ((int) ($a['response_time_ms'] ?? 0) > 3000) {
                $add('slow', 'notice', 'زمان پاسخ کند است ('.$a['response_time_ms'].'ms).');
            }
        }

        return [
            'issues' => $issues,
            'severity' => $this->overall($issues),
            'score' => $this->score($issues),
        ];
    }

    /**
     * پاس پس از پایان run: مشکلات canonical که نیازمند داده‌های میان‌ردیفی هستند را
     * محاسبه و به issues هر ردیف اضافه می‌کند، سپس severity/score را بازمحاسبه می‌کند.
     *
     * این روش idempotent است: ابتدا هر کد canonical قبلی حذف می‌شود تا اجرای دوباره
     * باعث شمارش مضاعف نشود. نقشهٔ مسیر→{noindex,status} یک‌بار برای کل run ساخته
     * می‌شود و قواعد ریدایرکت یک‌بار خوانده می‌شوند (بدون درخواست HTTP اضافه).
     */
    public function analyzeRun(SeoAuditRun $run): void
    {
        $rows = $run->audits()
            ->get(['id', 'url', 'canonical', 'is_noindex', 'status_code', 'issues']);

        if ($rows->isEmpty()) {
            return;
        }

        // نقشهٔ مسیر-نرمال → {noindex, status} برای cross-reference یک‌بار ساخته می‌شود.
        $map = [];
        foreach ($rows as $r) {
            $path = $this->pathOf((string) $r->url);
            if ($path === null) {
                continue;
            }
            // اولین ردیف برای هر مسیر برنده است.
            if (! isset($map[$path])) {
                $map[$path] = [
                    'noindex' => (bool) $r->is_noindex,
                    'status' => (int) $r->status_code,
                ];
            }
        }

        $rules = $this->redirects?->activeRulesForAnalysis() ?? [];
        $siteHost = $this->siteHost();

        foreach ($rows as $r) {
            $flags = $this->canonicalFlags(
                canonical: $r->canonical !== null ? (string) $r->canonical : null,
                isNoindex: (bool) $r->is_noindex,
                statusCode: (int) $r->status_code,
                map: $map,
                rules: $rules,
                siteHost: $siteHost,
            );

            // issues موجود (cast=array) را گرفته، کدهای canonical_* قبلی را پاک و دوباره می‌سازیم.
            $existing = is_array($r->issues) ? $r->issues : [];
            $existing = array_values(array_filter(
                $existing,
                fn ($i) => ! in_array($i['code'] ?? '', self::CANONICAL_CODES, true)
            ));

            $merged = array_merge($existing, $flags);

            SeoAudit::query()->whereKey($r->id)->update([
                'issues' => json_encode($merged, JSON_UNESCAPED_UNICODE),
                'severity' => $this->overall($merged),
                'score' => $this->score($merged),
            ]);
        }

        // شمارش‌های run را با شدت‌های به‌روزشده هماهنگ می‌کنیم.
        $this->recountRun($run);
    }

    /**
     * مشکلات canonical یک ردیف را بر اساس نقشهٔ میان‌ردیفی و قواعد ریدایرکت می‌سازد.
     *
     * @param  array<string,array{noindex:bool,status:int}>  $map
     * @param  iterable<array{source:string,match_type:string}>  $rules
     * @return list<array{code:string,severity:string,message:string}>
     */
    private function canonicalFlags(?string $canonical, bool $isNoindex, int $statusCode, array $map, iterable $rules, ?string $siteHost): array
    {
        $flags = [];
        $add = function (string $code, string $severity, string $message) use (&$flags) {
            $flags[] = compact('code', 'severity', 'message');
        };

        $canonical = $canonical !== null ? trim($canonical) : '';

        // فقط صفحات قابل‌ایندکس (non-noindex) را برای canonical خالی هشدار می‌دهیم.
        if ($canonical === '') {
            if (! $isNoindex && $statusCode >= 200 && $statusCode < 300) {
                $add('canonical_empty', 'warning', 'صفحهٔ قابل‌ایندکس تگ canonical ندارد.');
            }

            return $flags;
        }

        // canonical خارج از دامنه (host متفاوت با canonical_base_url) — اعلان کم‌اهمیت.
        $canonHost = parse_url($canonical, PHP_URL_HOST);
        if ($siteHost !== null && $canonHost !== null && $canonHost !== '' && strtolower($canonHost) !== $siteHost) {
            $add('canonical_offsite', 'notice', 'canonical به دامنهٔ دیگری اشاره می‌کند ('.$canonHost.').');
        }

        $path = $this->pathOf($canonical);
        if ($path === null) {
            return $flags;
        }

        // canonical به مبدأ یک ریدایرکت فعال اشاره می‌کند.
        if ($this->redirects !== null && $this->redirects->firstMatch($rules, $path) !== null) {
            $add('canonical_to_redirect', 'warning', 'canonical به یک مسیر ریدایرکت‌شونده اشاره می‌کند.');
        }

        // canonical به یک URL دیگرِ همین run اشاره می‌کند که noindex یا خطادار است.
        $target = $map[$path] ?? null;
        if ($target !== null) {
            if ($target['noindex']) {
                $add('canonical_to_noindex', 'warning', 'canonical به صفحه‌ای با noindex اشاره می‌کند.');
            }
            if ($target['status'] >= 400) {
                $add('canonical_to_404', 'critical', 'canonical به صفحه‌ای با خطا اشاره می‌کند ('.$target['status'].').');
            }
        }

        return $flags;
    }

    /** مسیر نرمال‌شده (با trailing-slash یکدست) از یک URL یا مسیر نسبی. */
    private function pathOf(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null || $path === false || $path === '') {
            $path = str_starts_with($url, '/') ? $url : '/';
        }
        $path = rtrim((string) $path, '/');

        return $path === '' ? '/' : $path;
    }

    /** host دامنهٔ canonical سایت (lowercase) یا null. */
    private function siteHost(): ?string
    {
        $base = SeoSetting::siteUrl();
        $host = parse_url($base, PHP_URL_HOST);

        return $host ? strtolower($host) : null;
    }

    /** شمارش شدت/میانگین امتیاز run را پس از تغییر ردیف‌ها بازمحاسبه می‌کند. */
    private function recountRun(SeoAuditRun $run): void
    {
        $rows = $run->audits()->get(['severity', 'score']);
        $counts = ['good' => 0, 'notice' => 0, 'warning' => 0, 'critical' => 0];
        $scoreSum = 0;
        foreach ($rows as $r) {
            $sev = (string) $r->severity;
            if (isset($counts[$sev])) {
                $counts[$sev]++;
            }
            $scoreSum += (int) $r->score;
        }
        $total = $rows->count();

        $run->update([
            'ok_count' => $counts['good'],
            'notice_count' => $counts['notice'],
            'warning_count' => $counts['warning'],
            'critical_count' => $counts['critical'],
            'avg_score' => $total > 0 ? (int) round($scoreSum / $total) : 0,
        ]);
    }

    /** @param list<array{severity:string}> $issues */
    private function overall(array $issues): string
    {
        $max = 'good';
        foreach ($issues as $i) {
            if ((self::RANK[$i['severity']] ?? 0) > self::RANK[$max]) {
                $max = $i['severity'];
            }
        }

        return $max;
    }

    /** @param list<array{severity:string}> $issues */
    private function score(array $issues): int
    {
        $score = 100;
        foreach ($issues as $i) {
            $score -= self::PENALTY[$i['severity']] ?? 0;
        }

        return max(0, min(100, $score));
    }
}
