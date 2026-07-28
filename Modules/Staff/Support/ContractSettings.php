<?php

namespace Modules\Staff\Support;

use App\Models\Setting;

/**
 * مشخصات «طرف اول» (مجموعه) و تصاویر مهر/امضای مدیریت.
 *
 * مقادیر از جدول تنظیمات خوانده می‌شوند و در نبودشان پیش‌فرضِ قرارداد
 * پایه استفاده می‌شود؛ پس بدون هیچ تنظیمی هم قرارداد کامل تولید می‌شود.
 */
final class ContractSettings
{
    /** پیش‌فرض‌ها بر اساس قرارداد پایهٔ مجموعه. */
    private const DEFAULTS = [
        'contract_first_party_name' => 'آقای علی اسماعیلی آهویی',
        'contract_first_party_national_code' => '0519826337',
        'contract_first_party_address' => 'تهران، خ سهروردی شمالی، ک زمانی، پ ۱۱، واحد ۱۸',
        'contract_first_party_phone' => '09914084683',
        'contract_first_party_role' => 'مدیرعامل',
        'contract_stamp_path' => '',      // مسیر تصویر مهر و امضا (اختیاری)
    ];

    public static function get(string $key): string
    {
        $value = Setting::get($key);

        return filled($value) ? (string) $value : (self::DEFAULTS[$key] ?? '');
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $out[$key] = self::get($key);
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    /** مسیر فایل مهر و امضا روی دیسک (در صورت وجود) — برای درج در PDF. */
    public static function stampFile(): ?string
    {
        $path = self::get('contract_stamp_path');
        if (blank($path)) {
            return null;
        }

        $full = storage_path('app/public/'.ltrim($path, '/'));

        return is_file($full) ? $full : null;
    }
}
