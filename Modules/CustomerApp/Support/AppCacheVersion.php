<?php

namespace Modules\CustomerApp\Support;

use App\Models\Setting;

/**
 * نسخهٔ کشِ اپ — یک مقدارِ یکتا که با هر تغییرِ داده‌های نیمه‌ثابتِ اپ
 * (کاتالوگ/برند/بنر/استان‌وشهر) بالا می‌رود. اپ آن را در /v1/customer/status
 * می‌بیند و اگر عوض شده باشد، کشِ محلیِ خود را باطل می‌کند (Cache Purge).
 */
class AppCacheVersion
{
    public const KEY = 'app_cache_version';

    /** مقدارِ فعلی (رشته‌ای پایدار؛ '0' اگر هنوز هیچ bumpی نشده). */
    public static function current(): string
    {
        $v = Setting::get(self::KEY, '0');

        return (string) ($v !== null && $v !== '' ? $v : '0');
    }

    /** نسخه را به «الان» می‌برد — تغییر تضمین‌شده تا اپ کش را باطل کند. */
    public static function bump(): void
    {
        Setting::set(self::KEY, (string) now()->timestamp);
    }
}
