<?php

namespace Modules\Seo\Support;

/**
 * فهرست متمرکزِ دسترسی‌های ریزدانهٔ سئو و برچسب‌های فارسی آن‌ها.
 *
 * این کلاس صرفاً منبعِ حقیقتِ کلیدها/برچسب‌ها برای migration، کنترلر و ویو است
 * تا منطقِ نمایش از داده جدا بماند.
 */
class SeoPermissions
{
    /** دسترسیِ کلیِ سئو (gate مسیرها) — حذف نمی‌شود. */
    public const MANAGE = 'manage-seo';

    /**
     * پنج دسترسیِ ریزدانه به‌همراه برچسب فارسی و توضیح کوتاه.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function labels(): array
    {
        return [
            'seo-view' => [
                'label' => 'مشاهدهٔ سئو',
                'description' => 'دیدنِ داشبورد، گزارش‌ها و تنظیمات (فقط‌خواندنی).',
            ],
            'seo-edit-content' => [
                'label' => 'ویرایش محتوا',
                'description' => 'عنوان، توضیحات، متاهای OG و محتوا.',
            ],
            'seo-manage-redirects' => [
                'label' => 'مدیریت ریدایرکت‌ها',
                'description' => 'ریدایرکت‌ها و مانیتور ۴۰۴.',
            ],
            'seo-manage-technical' => [
                'label' => 'مدیریت فنی',
                'description' => 'robots، schema، sitemap، canonical و IndexNow.',
            ],
            'seo-manage-settings' => [
                'label' => 'مدیریت تنظیمات',
                'description' => 'تنظیمات کلی، تأیید مالکیت، یکپارچه‌سازی‌ها و نقش‌ها.',
            ],
        ];
    }

    /**
     * فقط کلیدهای پنج دسترسی ریزدانه.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::labels());
    }

    /**
     * نگاشتِ نقش‌های پیشنهادی به دسترسی‌هایشان (شاملِ manage-seo برای عبور از gate مسیرها).
     *
     * @return array<string, array<int, string>>
     */
    public static function roleMap(): array
    {
        return [
            'seo-super-admin' => array_merge([self::MANAGE], self::keys()),
            'seo-manager' => array_merge([self::MANAGE], self::keys()),
            'seo-content-editor' => [self::MANAGE, 'seo-view', 'seo-edit-content'],
            'seo-developer' => [self::MANAGE, 'seo-view', 'seo-manage-technical'],
            'seo-viewer' => [self::MANAGE, 'seo-view'],
        ];
    }
}
