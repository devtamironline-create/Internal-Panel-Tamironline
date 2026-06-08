<?php

namespace Modules\Site\Support;

use Illuminate\Support\Str;

/**
 * نرمالایز کردن مقادیر تصویر برای پاسخ API: مقدارهای ذخیره‌شده ممکن است
 * یک URL کامل، یک مسیر مطلق (/storage/...) یا یک مسیر نسبی روی disk
 * public باشند (مثلاً "site/devices/abc.png"). در تمام موارد یک URL
 * کامل قابل‌استفاده در فرانت برمی‌گردانیم.
 */
final class MediaUrl
{
    public static function resolve(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }
        if (Str::startsWith($value, '/')) {
            return $value;
        }
        return asset('storage/' . ltrim($value, '/'));
    }
}
