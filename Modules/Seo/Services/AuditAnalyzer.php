<?php

namespace Modules\Seo\Services;

/**
 * تبدیل سیگنال‌های خام آدیت به فهرست مشکلات با شدت + امتیاز کلی.
 * pure و واحد-تست‌پذیر.
 */
class AuditAnalyzer
{
    private const RANK = ['good' => 0, 'notice' => 1, 'warning' => 2, 'critical' => 3];

    private const PENALTY = ['notice' => 5, 'warning' => 15, 'critical' => 40];

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
