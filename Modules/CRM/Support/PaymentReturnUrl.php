<?php

namespace Modules\CRM\Support;

use Modules\CRM\Models\CrmSetting;

/**
 * امن‌سازیِ «لینکِ برگشت به اپ» بعد از پرداخت.
 *
 * بدونِ allowlist این یک open-redirect تمام‌عیار است: هر کسی می‌توانست
 * لینکِ پرداختِ واقعی بسازد که بعد از پرداختِ موفق، قربانی را به سایتِ
 * خودش بفرستد. پس فقط scheme/دامنه‌هایی که صریحاً مجاز شده‌اند عبور
 * می‌کنند؛ بقیه بی‌سروصدا حذف می‌شوند (پرداخت نمی‌شکند، فقط برگشتِ
 * خودکار ندارد).
 *
 * تنظیم: crm_settings کلید `payment_return_whitelist` — با کاما جدا:
 *   - scheme کامل مثل `karbalad://` یا `tamironline://`
 *   - دامنهٔ https مثل `app.tamironline.com` (زیردامنه‌هایش هم مجازند)
 */
final class PaymentReturnUrl
{
    public const SETTING_KEY = 'payment_return_whitelist';

    public const DEFAULT_WHITELIST = 'tamironline://,karbalad://,app.tamironline.com,karbalad.tamironline.com,panel.tamironline.com';

    public static function sanitize(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || mb_strlen($url) > 500) {
            return null;
        }
        // نویسه‌های کنترلی/فاصله = دستکاری؛ کل لینک دور انداخته می‌شود.
        if (preg_match('/[\s\x00-\x1f]/u', $url)) {
            return null;
        }

        foreach (self::whitelist() as $entry) {
            if (str_contains($entry, '://')) {
                // schemeِ اختصاصیِ اپ — فقط پیشوندِ دقیق.
                if (str_starts_with(mb_strtolower($url), mb_strtolower($entry))) {
                    return $url;
                }

                continue;
            }

            // دامنهٔ https — خودِ دامنه یا زیردامنه‌اش.
            $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
            $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));
            $entry = mb_strtolower($entry);
            if ($scheme === 'https' && $host !== '' && ($host === $entry || str_ends_with($host, '.'.$entry))) {
                return $url;
            }
        }

        return null;
    }

    /** الحاقِ نتیجهٔ پرداخت به لینکِ برگشت — با رعایتِ ? یا & موجود. */
    public static function withResult(string $url, bool $ok, ?string $reference = null): string
    {
        $params = 'payment='.($ok ? 'success' : 'failed')
            .($reference !== null && $reference !== '' ? '&reference='.rawurlencode($reference) : '');

        return $url.(str_contains($url, '?') ? '&' : '?').$params;
    }

    /** @return list<string> */
    private static function whitelist(): array
    {
        $raw = (string) (CrmSetting::get(self::SETTING_KEY) ?: self::DEFAULT_WHITELIST);

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
