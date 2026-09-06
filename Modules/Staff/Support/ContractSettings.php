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

        // ── بندهای ثابتِ قرارداد ──
        //
        // این‌ها قبلاً در فرمِ صدور قابلِ تغییر بودند، که غلط بود: متنِ قراردادِ
        // پایه آن‌ها را ثابت اعلام کرده و متغیر بودنشان یعنی دو کارمند با دو
        // شرطِ حقوقیِ متفاوت. حالا یک مقدار برای کلِ مجموعه است و اگر روزی
        // قرارداد بازنویسی شد، از همین‌جا یک‌بار عوض می‌شود.
        //
        // تنها چیزهایی که به‌ازای هر نفر متغیرند: مزد ماهانه، شماره سفته و
        // مبلغ سفته.
        'contract_non_solicit_months' => '24',    // بند ۱۳-۱ — مدت عدم جذب
        'contract_payment_grace_days' => '3',     // بند ۷-۱ — مهلت بررسی تأخیر پرداخت
        'contract_confidentiality_years' => '5',  // بند ۱۰-۵ — مدت محرمانگی
        'contract_holiday_hourly_rate' => '',     // بند ۵-۲ — نرخ ساعتی روز تعطیل (تومان)

        // ── اعدادِ پایهٔ جدولِ حقوقِ قراردادِ نسخه ۲ (به ریال) ──
        // بقیهٔ جدول (جمع روزانه، ۳۰ و ۳۱ روزه، جمع کل) از این‌ها محاسبه
        // می‌شود. پیش‌فرض‌ها مقادیرِ قانونیِ سالِ ۱۴۰۵‌اند و از این‌جا یک‌بار
        // برای کلِ مجموعه به‌روز می‌شوند (موقعِ صدور روی رکورد snapshot می‌شوند).
        'contract_v2_daily_wage' => '5541850',       // دستمزد روزانه مبنای قرارداد و بیمه
        'contract_v2_daily_seniority' => '166667',   // پایه سنوات روزانه
        'contract_v2_monthly_benefits' => '52000000', // مزایای ماهانه مشمول (بن، مسکن، رفاهی)
        'contract_v2_marriage_allowance' => '5000000', // حق تأهل ماهانه
        // توجه: حق اولادِ یک فرزند مشتق است (۳× دستمزد روزانه) و تنظیمِ جدا ندارد.
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

    /** مقدارِ عددیِ یک بندِ ثابت — برای پرکردنِ ستون‌ها هنگام صدور. */
    public static function int(string $key): ?int
    {
        $value = self::get($key);

        return $value === '' ? null : (int) $value;
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
