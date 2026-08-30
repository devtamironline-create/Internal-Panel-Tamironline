<?php

namespace Modules\CRM\Support;

use Illuminate\Support\Str;

/**
 * ساختِ slugِ انگلیسیِ (kebab-case) شهر از نامِ فارسی — تا آدرس‌های سئو مثل
 * /city/karaj/... انگلیسی بمانند (نه /city/کرج/...).
 *
 * اولویت: نگاشتِ آماده برای شهرهای پرکاربرد ← Str::slug ← ترنسلیتِ حرف‌به‌حرف.
 */
class CitySlug
{
    /** الگوی slugِ معتبر (انگلیسیِ kebab). */
    public const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** نگاشتِ شهرهای پرکاربرد (مراکز استان + شهرهای بزرگ). */
    private const MAP = [
        'تهران' => 'tehran', 'مشهد' => 'mashhad', 'اصفهان' => 'isfahan', 'کرج' => 'karaj',
        'شیراز' => 'shiraz', 'تبریز' => 'tabriz', 'قم' => 'qom', 'اهواز' => 'ahvaz',
        'کرمانشاه' => 'kermanshah', 'ارومیه' => 'urmia', 'رشت' => 'rasht', 'زاهدان' => 'zahedan',
        'همدان' => 'hamedan', 'کرمان' => 'kerman', 'یزد' => 'yazd', 'اردبیل' => 'ardabil',
        'بندرعباس' => 'bandar-abbas', 'اراک' => 'arak', 'زنجان' => 'zanjan', 'سنندج' => 'sanandaj',
        'قزوین' => 'qazvin', 'خرم‌آباد' => 'khorramabad', 'خرم آباد' => 'khorramabad',
        'گرگان' => 'gorgan', 'ساری' => 'sari', 'بوشهر' => 'bushehr', 'بیرجند' => 'birjand',
        'بجنورد' => 'bojnord', 'یاسوج' => 'yasuj', 'شهرکرد' => 'shahrekord', 'ایلام' => 'ilam',
        'سمنان' => 'semnan', 'کیش' => 'kish', 'قشم' => 'qeshm', 'کاشان' => 'kashan',
        'نجف‌آباد' => 'najafabad', 'نجف آباد' => 'najafabad', 'ورامین' => 'varamin',
        'اسلامشهر' => 'eslamshahr', 'پاکدشت' => 'pakdasht', 'قدس' => 'qods', 'ملارد' => 'malard',
        'شهریار' => 'shahriar', 'دماوند' => 'damavand', 'پردیس' => 'pardis', 'رباط‌کریم' => 'robat-karim',
        'بابل' => 'babol', 'آمل' => 'amol', 'قائم‌شهر' => 'qaemshahr', 'قائم شهر' => 'qaemshahr',
        'نیشابور' => 'neyshabur', 'سبزوار' => 'sabzevar', 'دزفول' => 'dezful', 'آبادان' => 'abadan',
        'بروجرد' => 'borujerd', 'مرودشت' => 'marvdasht', 'ماهشهر' => 'mahshahr', 'گنبد کاووس' => 'gonbad-kavus',
    ];

    /** ترنسلیتِ حرف‌به‌حرفِ فارسی→لاتین برای شهرهای خارج از نگاشت. */
    private const CHARS = [
        'آ' => 'a', 'ا' => 'a', 'أ' => 'a', 'إ' => 'e', 'ب' => 'b', 'پ' => 'p', 'ت' => 't', 'ث' => 's',
        'ج' => 'j', 'چ' => 'ch', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'z', 'ر' => 'r', 'ز' => 'z',
        'ژ' => 'zh', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'z', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a',
        'غ' => 'gh', 'ف' => 'f', 'ق' => 'gh', 'ک' => 'k', 'ك' => 'k', 'گ' => 'g', 'ل' => 'l', 'م' => 'm',
        'ن' => 'n', 'و' => 'v', 'ه' => 'h', 'ة' => 'h', 'ی' => 'y', 'ي' => 'y', 'ئ' => 'y', 'ء' => '',
        'ّ' => '', 'َ' => 'a', 'ِ' => 'e', 'ُ' => 'o', 'ْ' => '', 'ٔ' => '',
    ];

    /**
     * slugِ انگلیسیِ نهایی. اگر $preferred (ورودیِ ادمین) انگلیسیِ معتبر باشد
     * همان استفاده می‌شود؛ وگرنه از نامِ شهر ساخته می‌شود.
     */
    public static function make(string $cityName, ?string $preferred = null): string
    {
        $preferred = trim((string) $preferred);
        // فقط ورودیِ ASCII (انگلیسیِ ادمین) پذیرفته می‌شود؛ ورودیِ فارسی نادیده
        // گرفته و از نگاشتِ نام ساخته می‌شود (کرج → karaj، نه krg).
        if ($preferred !== '' && preg_match('/^[\x20-\x7E]+$/', $preferred)) {
            $p = Str::slug($preferred);
            if ($p !== '' && preg_match(self::PATTERN, $p)) {
                return $p;
            }
        }

        return self::fromName($cityName);
    }

    /** slug از نامِ شهر — نگاشت ← Str::slug ← ترنسلیتِ حرف‌به‌حرف. */
    public static function fromName(string $cityName): string
    {
        $name = trim($cityName);

        if ($name !== '' && isset(self::MAP[$name])) {
            return self::MAP[$name];
        }

        $ascii = Str::slug($name);
        if ($ascii !== '' && preg_match(self::PATTERN, $ascii)) {
            return $ascii;
        }

        $translit = strtr($name, self::CHARS);

        return Str::slug($translit);
    }

    public static function isValid(?string $slug): bool
    {
        return is_string($slug) && preg_match(self::PATTERN, $slug) === 1;
    }
}
