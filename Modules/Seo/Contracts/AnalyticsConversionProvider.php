<?php

namespace Modules\Seo\Contracts;

/** قرارداد دادهٔ تبدیل (لید تماس/ورود اپ) — فاز بعد: WindsorGa4Provider. */
interface AnalyticsConversionProvider
{
    /**
     * @return array{organic_leads: ?int, phone_call_leads: ?int, app_login_leads: ?int}
     */
    public function conversions(string $domain, string $from, string $to): array;
}
