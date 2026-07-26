<?php

namespace Modules\Seo\Support\Arm;

/**
 * منبعِ واحدِ مقادیر مجاز و برچسب‌های فارسیِ «بازوی سئو».
 * UI هیچ رشته‌ای را hardcode نمی‌کند — همه از اینجا.
 */
final class ArmEnums
{
    public const PRIORITIES = [
        'p0' => ['label' => 'بحرانی (P0)', 'color' => 'red'],
        'p1' => ['label' => 'بالا (P1)', 'color' => 'amber'],
        'p2' => ['label' => 'متوسط (P2)', 'color' => 'blue'],
        'p3' => ['label' => 'پایین (P3)', 'color' => 'gray'],
    ];

    public const PAGE_TYPES = [
        'homepage' => 'صفحهٔ اصلی',
        'service' => 'صفحهٔ خدمات',
        'brand_service' => 'خدمات برند',
        'article' => 'مقاله',
        'error_guide' => 'راهنمای خطا',
        'pricing' => 'قیمت‌ها',
        'category' => 'دسته‌بندی',
        'product' => 'محصول',
        'utility' => 'کاربردی',
    ];

    public const INTENTS = [
        'informational' => 'اطلاعاتی',
        'commercial' => 'تجاری',
        'transactional' => 'تراکنشی',
        'navigational' => 'ناوبری',
    ];

    public const PAGE_WORKFLOW = [
        'discovered' => 'شناسایی‌شده',
        'mapped' => 'نگاشت‌شده',
        'optimize' => 'در صف بهینه‌سازی',
        'writing' => 'در حال نگارش',
        'review' => 'در بازبینی',
        'ready' => 'آماده',
        'published' => 'منتشرشده',
        'monitoring' => 'در پایش',
        'refresh' => 'نیازمند به‌روزرسانی',
        'merge' => 'ادغام',
        'redirect' => 'ریدایرکت',
        'archived' => 'بایگانی',
    ];

    public const ROADMAP_STATUSES = [
        'planned' => 'برنامه‌ریزی‌شده',
        'ready' => 'آمادهٔ شروع',
        'in_progress' => 'در حال انجام',
        'review' => 'در بازبینی',
        'blocked' => 'مسدود',
        'completed' => 'انجام‌شده',
        'skipped' => 'ردشده',
    ];

    /** گذارهای مجاز وضعیت آیتم برنامه — جلوگیری از پرش نامعتبر. */
    public const ROADMAP_TRANSITIONS = [
        'planned' => ['ready', 'in_progress', 'skipped'],
        'ready' => ['in_progress', 'planned', 'skipped'],
        'in_progress' => ['review', 'blocked', 'completed', 'ready'],
        'review' => ['completed', 'in_progress', 'blocked'],
        'blocked' => ['ready', 'in_progress', 'skipped'],
        'completed' => ['in_progress'], // بازگشایی
        'skipped' => ['planned'],
    ];

    public const CONTENT_STATUSES = [
        'idea' => 'ایده',
        'brief' => 'بریف',
        'writing' => 'در حال نگارش',
        'expert_review' => 'بازبینی تخصصی',
        'seo_review' => 'بازبینی سئو',
        'ready' => 'آمادهٔ انتشار',
        'scheduled' => 'زمان‌بندی‌شده',
        'published' => 'منتشرشده',
        'monitoring' => 'در پایش',
        'refresh_required' => 'نیازمند به‌روزرسانی',
    ];

    public const CONTENT_TRANSITIONS = [
        'idea' => ['brief'],
        'brief' => ['writing', 'idea'],
        'writing' => ['expert_review', 'seo_review', 'brief'],
        'expert_review' => ['seo_review', 'writing'],
        'seo_review' => ['ready', 'writing'],
        'ready' => ['scheduled', 'published', 'seo_review'],
        'scheduled' => ['published', 'ready'],
        'published' => ['monitoring'],
        'monitoring' => ['refresh_required'],
        'refresh_required' => ['brief', 'writing'],
    ];

    public const CONTENT_TYPES = [
        'article' => 'مقالهٔ جدید',
        'refresh' => 'به‌روزرسانی محتوا',
        'service_page' => 'بهینه‌سازی صفحهٔ خدمات',
        'brand_page' => 'صفحهٔ برند',
        'landing' => 'لندینگ',
        'faq' => 'پرسش و پاسخ',
        'internal_linking' => 'لینک‌سازی داخلی',
    ];

    public const OPPORTUNITY_TYPES = [
        'striking_distance' => 'در یک قدمی صدر (رتبه ۴–۱۰)',
        'low_ctr' => 'CTR پایین‌تر از انتظار',
        'content_gap' => 'خلأ محتوایی',
        'cannibalization' => 'هم‌نوع‌خواری کلمات',
        'commercial_query' => 'کوئری تجاری کم‌بازده',
        'internal_link' => 'لینک داخلی',
        'lost_keyword' => 'کلمهٔ ازدست‌رفته',
        'declining_page' => 'صفحهٔ در حال افت',
        'conversion_gap' => 'خلأ تبدیل',
    ];

    public const OPPORTUNITY_STATUSES = [
        'open' => 'باز',
        'in_progress' => 'در حال اقدام',
        'done' => 'انجام‌شده',
        'dismissed' => 'منتفی',
    ];

    public const ISSUE_CATEGORIES = [
        'indexability' => 'ایندکس‌پذیری',
        'canonical' => 'کنونیکال',
        'duplicate' => 'محتوای تکراری',
        'metadata' => 'متادیتا',
        'internal_links' => 'لینک‌های داخلی',
        'broken_link' => 'لینک خراب',
        'redirect' => 'ریدایرکت',
        'pagination' => 'صفحه‌بندی',
        'parameter_url' => 'URL پارامتردار',
        'schema' => 'اسکیما',
        'performance' => 'سرعت و عملکرد',
        'content_quality' => 'کیفیت محتوا',
    ];

    public const ISSUE_SEVERITIES = [
        'critical' => ['label' => 'بحرانی', 'color' => 'red'],
        'high' => ['label' => 'بالا', 'color' => 'amber'],
        'medium' => ['label' => 'متوسط', 'color' => 'blue'],
        'low' => ['label' => 'پایین', 'color' => 'gray'],
    ];

    public const ISSUE_STATUSES = [
        'needs_verification' => 'نیازمند تأیید',
        'open' => 'باز',
        'in_progress' => 'در حال رفع',
        'resolved' => 'رفع‌شده',
        'ignored' => 'نادیده',
    ];

    public const LINK_TYPES = [
        'internal' => 'داخلی',
        'external' => 'خارجی',
        'digital_pr' => 'روابط عمومی دیجیتال',
        'citation' => 'سایتیشن',
    ];

    public const LINK_STATUSES = [
        'planned' => 'برنامه‌ریزی‌شده',
        'outreach' => 'در حال مذاکرهٔ اولیه',
        'negotiation' => 'در حال توافق',
        'placed' => 'درج‌شده',
        'live' => 'فعال',
        'rejected' => 'ردشده',
        'removed' => 'حذف‌شده',
    ];

    public const PHASES = [
        1 => ['title' => 'فاز ۱ — پایه‌گذاری داده', 'days' => '۱ تا ۱۴', 'range' => [1, 14]],
        2 => ['title' => 'فاز ۲ — رفع فنی و صفحات تجاری', 'days' => '۱۵ تا ۳۰', 'range' => [15, 30]],
        3 => ['title' => 'فاز ۳ — بردهای سریع محتوای موجود', 'days' => '۳۱ تا ۵۰', 'range' => [31, 50]],
        4 => ['title' => 'فاز ۴ — گسترش خوشه‌های محتوا', 'days' => '۵۱ تا ۷۰', 'range' => [51, 70]],
        5 => ['title' => 'فاز ۵ — لینک‌سازی و اعتبار', 'days' => '۷۱ تا ۸۳', 'range' => [71, 83]],
        6 => ['title' => 'فاز ۶ — اندازه‌گیری و برنامهٔ بعد', 'days' => '۸۴ تا ۹۰', 'range' => [84, 90]],
    ];

    public const TASK_TYPES = [
        'analysis' => 'تحلیل',
        'technical' => 'فنی',
        'content_new' => 'محتوای جدید',
        'content_refresh' => 'به‌روزرسانی محتوا',
        'service_optimization' => 'بهینه‌سازی صفحهٔ خدمات',
        'internal_linking' => 'لینک‌سازی داخلی',
        'link_building' => 'لینک‌سازی خارجی',
        'keyword_mapping' => 'نگاشت کلمات',
        'measurement' => 'اندازه‌گیری و گزارش',
        'milestone' => 'مایل‌استون هفتگی',
        'planning' => 'برنامه‌ریزی',
    ];

    /** برچسب یک مقدار از هر گروهِ ثابت (fallback: خود مقدار). */
    public static function label(array $map, ?string $key): string
    {
        if ($key === null) {
            return '—';
        }
        $val = $map[$key] ?? $key;

        return is_array($val) ? ($val['label'] ?? $key) : $val;
    }
}
