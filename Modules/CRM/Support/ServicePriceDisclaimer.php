<?php

namespace Modules\CRM\Support;

use Modules\CRM\Models\CrmSetting;

/**
 * «نکات مهم دربارهٔ تعرفه‌ها» — یک متنِ توضیحیِ سایت‌گستر که زیرِ تعرفه‌ها در
 * صفحهٔ دستگاه، صفحهٔ device×brand و /pricing نمایش داده می‌شود.
 *
 * یک مقدار، یک‌جا مدیریت می‌شود (کلیدِ JSON در crm_settings). تا وقتی مدیر
 * چیزی ذخیره نکرده، API این فیلد را خالی می‌فرستد و فرانت متنِ پیش‌فرضِ خودش
 * را نشان می‌دهد (همان متنِ زیر). بعد از ذخیره، مقدارِ پنل همه‌جا غالب می‌شود.
 */
class ServicePriceDisclaimer
{
    public const KEY = 'service_price_disclaimer';

    public const DEFAULT_TITLE = 'نکات مهم دربارهٔ تعرفه‌ها';

    /** @var array<int, string> */
    public const DEFAULT_PARAGRAPHS = [
        'قیمت‌های درج‌شده، مربوط به اجرت و دستمزد خدمات هستند و شامل هزینهٔ قطعات نمی‌شوند. در صورت نیاز به تعویض قطعه، هزینهٔ قطعه با توجه به برند، مدل دستگاه، نوع قطعه و نرخ روز بازار، پس از عیب‌یابی در محل توسط تکنسین اعلام می‌شود.',
        'اجرت خدمات در تعمیرآنلاین بر اساس لیست تعرفهٔ اعلام‌شده محاسبه می‌شود و تکنسین مجاز به اعلام قیمت خارج از چارچوب تعرفه نیست. با این حال، در مواردی که نوع خرابی، حجم کار یا شرایط دستگاه با سرویس انتخاب‌شده متفاوت باشد، مبلغ نهایی اجرت پس از بررسی دستگاه و پیش از انجام تعمیر به مشتری اعلام خواهد شد.',
        'تعمیرآنلاین تلاش می‌کند خدمات تخصصی لوازم خانگی را با اجرت منصفانه، کمترین هزینهٔ ممکن و کیفیت بالای اجرا ارائه دهد. رویکرد ما اعلام شفاف و واقعی هزینه‌هاست؛ نه ارائهٔ قیمت‌های وسوسه‌کننده و غیرواقعی که در زمان انجام تعمیر با هزینه‌های اضافه یا غیرمنتظره جبران شوند.',
    ];

    /**
     * مقدارِ ذخیره‌شده برای API — اگر مدیر چیزی ذخیره نکرده یا هیچ پاراگرافی
     * ندارد، null برمی‌گرداند تا فیلد در پاسخ حذف شود و فرانت پیش‌فرض را بزند.
     *
     * @return array{title:string, paragraphs:array<int,string>}|null
     */
    public static function forApi(): ?array
    {
        $raw = CrmSetting::getJson(self::KEY);
        if (! is_array($raw)) {
            return null;
        }

        $paragraphs = self::normalizeParagraphs($raw['paragraphs'] ?? []);
        if ($paragraphs === []) {
            return null;
        }

        $title = trim((string) ($raw['title'] ?? ''));

        return [
            'title' => $title !== '' ? $title : self::DEFAULT_TITLE,
            'paragraphs' => $paragraphs,
        ];
    }

    /**
     * مقدار برای نمایش در فرمِ پنل — اگر چیزی ذخیره نشده، متنِ پیش‌فرض را می‌دهد
     * تا مدیر آن را ببیند و ویرایش کند.
     *
     * @return array{title:string, paragraphs:array<int,string>, is_custom:bool}
     */
    public static function forForm(): array
    {
        $raw = CrmSetting::getJson(self::KEY);
        $paragraphs = is_array($raw) ? self::normalizeParagraphs($raw['paragraphs'] ?? []) : [];
        $isCustom = $paragraphs !== [];

        return [
            'title' => $isCustom ? trim((string) ($raw['title'] ?? '')) ?: self::DEFAULT_TITLE : self::DEFAULT_TITLE,
            'paragraphs' => $isCustom ? $paragraphs : self::DEFAULT_PARAGRAPHS,
            'is_custom' => $isCustom,
        ];
    }

    /**
     * ذخیرهٔ مقدار. عنوانِ خالی → پیش‌فرض. آرایهٔ پاراگرافِ خالی → پاک‌کردنِ کلید
     * (بازگشت به پیش‌فرضِ فرانت).
     *
     * @param  array<int,string>  $paragraphs
     */
    public static function save(?string $title, array $paragraphs): void
    {
        $paragraphs = self::normalizeParagraphs($paragraphs);
        if ($paragraphs === []) {
            CrmSetting::where('key', self::KEY)->delete();

            return;
        }

        CrmSetting::setJson(self::KEY, [
            'title' => trim((string) $title) ?: self::DEFAULT_TITLE,
            'paragraphs' => $paragraphs,
        ]);
    }

    /**
     * پاراگراف‌ها را تمیز می‌کند: رشته‌ها یا {text:...}، حذفِ خالی‌ها.
     *
     * @param  mixed  $value
     * @return array<int, string>
     */
    private static function normalizeParagraphs($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $p) {
            if (is_array($p)) {
                $p = $p['text'] ?? '';
            }
            $p = trim((string) $p);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return $out;
    }
}
