<?php

namespace App\Support\ActivityLog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * منبعِ واحدِ برچسب‌های فارسی و قواعدِ فیلترِ گزارشِ فعالیت.
 * هیچ رشتهٔ فارسی‌ای در کنترلر/ویو hardcode نمی‌شود.
 */
class ActivityRegistry
{
    /** برچسبِ فارسیِ نوعِ اکشن. */
    public const ACTIONS = [
        'created' => 'ایجاد',
        'updated' => 'ویرایش',
        'deleted' => 'حذف',
        'restored' => 'بازگردانی',
        'force_deleted' => 'حذف کامل',
        'login' => 'ورود',
        'logout' => 'خروج',
        'login_failed' => 'ورود ناموفق',
        'assign' => 'تخصیص',
        'unassign' => 'لغو تخصیص',
    ];

    /** رنگِ معناییِ هر اکشن برای UI. */
    public const ACTION_COLORS = [
        'created' => 'emerald',
        'updated' => 'blue',
        'deleted' => 'red',
        'force_deleted' => 'red',
        'restored' => 'amber',
        'login' => 'gray',
        'logout' => 'gray',
        'login_failed' => 'red',
        'assign' => 'indigo',
        'unassign' => 'amber',
    ];

    /**
     * برچسبِ فارسیِ مدل‌ها. هر مدلی که اینجا نباشد با نامِ کلاسش نمایش
     * داده می‌شود (لاگ حذف نمی‌شود — فقط برچسب ندارد).
     *
     * @return array<class-string, string>
     */
    public static function modelLabels(): array
    {
        return [
            \App\Models\User::class => 'کاربر پنل',
            \App\Models\Setting::class => 'تنظیمات سیستم',
            \Spatie\Permission\Models\Role::class => 'نقش',
            \Spatie\Permission\Models\Permission::class => 'دسترسی',

            // CRM
            \Modules\CRM\Models\Order::class => 'سفارش',
            \Modules\CRM\Models\OrderItem::class => 'قلم سفارش',
            \Modules\CRM\Models\OrderAdminNote::class => 'یادداشت سفارش',
            \Modules\CRM\Models\OrderAssignment::class => 'تخصیص سفارش',
            \Modules\CRM\Models\OrderReview::class => 'نظرسنجی سفارش',
            \Modules\CRM\Models\Customer::class => 'مشتری',
            \Modules\CRM\Models\CustomerAddress::class => 'آدرس مشتری',
            \Modules\CRM\Models\Technician::class => 'تکنسین',
            \Modules\CRM\Models\TechnicianPercentChange::class => 'تغییر درصد تکنسین',
            \Modules\CRM\Models\Invoice::class => 'فاکتور',
            \Modules\CRM\Models\Proforma::class => 'پیش‌فاکتور',
            \Modules\CRM\Models\Payment::class => 'پرداخت',
            \Modules\CRM\Models\PaymentAccount::class => 'حساب پرداخت',
            \Modules\CRM\Models\Expense::class => 'هزینه',
            \Modules\CRM\Models\ExpenseCategory::class => 'دستهٔ هزینه',
            \Modules\CRM\Models\GoogleAdsEntry::class => 'ثبت گوگل ادز',
            \Modules\CRM\Models\Device::class => 'دستگاه',
            \Modules\CRM\Models\DeviceBrandPage::class => 'صفحهٔ ترکیبی دستگاه/برند',
            \Modules\CRM\Models\DeviceCategory::class => 'دستهٔ دستگاه',
            \Modules\CRM\Models\DeviceServicePrice::class => 'تعرفهٔ خدمات',
            \Modules\CRM\Models\Brand::class => 'برند',
            \Modules\CRM\Models\ServiceType::class => 'نوع خدمت',
            \Modules\CRM\Models\Objection::class => 'ایراد دستگاه',
            \Modules\CRM\Models\LeadReason::class => 'دلیل لید',
            \Modules\CRM\Models\Ticket::class => 'تیکت',
            \Modules\CRM\Models\TicketReply::class => 'پاسخ تیکت',
            \Modules\CRM\Models\TicketCategory::class => 'دستهٔ تیکت',
            \Modules\CRM\Models\SmsTemplate::class => 'قالب پیامک',
            \Modules\CRM\Models\CrmSetting::class => 'تنظیمات CRM',
            \Modules\CRM\Models\Holiday::class => 'تعطیلات',
            \Modules\CRM\Models\City::class => 'شهر',
            \Modules\CRM\Models\Province::class => 'استان',
            \Modules\CRM\Models\Announcement::class => 'اطلاعیه',
            \Modules\CRM\Models\BaleCampaign::class => 'کمپین بله',

            // انبار
            \Modules\Warehouse\Models\WarehouseOrder::class => 'سفارش انبار',
            \Modules\Warehouse\Models\WarehouseProduct::class => 'محصول انبار',
            \Modules\Warehouse\Models\WarehouseSetting::class => 'تنظیمات انبار',
            \Modules\Warehouse\Models\WarehouseShippingType::class => 'نوع ارسال',
            \Modules\Warehouse\Models\WarehouseBoxSize::class => 'اندازهٔ کارتن',

            // سایت و محتوا
            \Modules\Site\Models\Article::class => 'مقاله',
            \Modules\Site\Models\Page::class => 'صفحهٔ سایت',
            \Modules\Site\Models\PageSection::class => 'سکشن صفحه',
            \Modules\Site\Models\Faq::class => 'پرسش متداول',
            \Modules\Site\Models\Comment::class => 'دیدگاه',
            \Modules\Site\Models\Review::class => 'نظر مشتری',
            \Modules\Site\Models\Banner::class => 'بنر',
            \Modules\Site\Models\BlogTopic::class => 'موضوع بلاگ',
            \Modules\Site\Models\Taxonomy::class => 'دسته‌بندی محتوا',
            \Modules\Site\Models\SiteSetting::class => 'تنظیمات سایت',
            \Modules\Site\Models\ContactMessage::class => 'پیام تماس',
            \Modules\Site\Models\Forum\Question::class => 'پرسش انجمن',
            \Modules\Site\Models\Forum\Answer::class => 'پاسخ انجمن',
            \Modules\Site\Models\Media\Media::class => 'فایل رسانه',

            // سئو
            \Modules\Seo\Models\SeoMeta::class => 'متای سئو',
            \Modules\Seo\Models\SeoRedirect::class => 'ریدایرکت',
            \Modules\Seo\Models\SeoSetting::class => 'تنظیمات سئو',
            \Modules\Seo\Models\SeoProject::class => 'پروژهٔ سئو',
            \Modules\Seo\Models\SeoPage::class => 'صفحهٔ بازوی سئو',
            \Modules\Seo\Models\SeoKeyword::class => 'کلمهٔ کلیدی',
            \Modules\Seo\Models\SeoRoadmapItem::class => 'کار برنامهٔ سئو',
            \Modules\Seo\Models\SeoContentItem::class => 'آیتم تقویم محتوا',
            \Modules\Seo\Models\SeoOpportunity::class => 'فرصت سئو',
            \Modules\Seo\Models\SeoIssue::class => 'مشکل فنی سئو',
            \Modules\Seo\Models\SeoLinkItem::class => 'آیتم لینک‌سازی',

            // کارکنان و مالی
            \Modules\Attendance\Models\Attendance::class => 'تردد',
            \Modules\Attendance\Models\LeaveRequest::class => 'درخواست مرخصی',
            \Modules\Salary\Models\Salary::class => 'حقوق',
            \Modules\Technician\Models\TechnicianRegistration::class => 'ثبت‌نام تکنسین',
            \Modules\Task\Models\Task::class => 'وظیفه',
            \Modules\OKR\Models\Objective::class => 'هدف OKR',
            \Modules\OKR\Models\KeyResult::class => 'نتیجهٔ کلیدی',
        ];
    }

    /** برچسبِ فارسیِ یک مدل (fallback: نامِ کوتاهِ کلاس). */
    public static function label(?string $class): string
    {
        if (! $class) {
            return '—';
        }

        return static::modelLabels()[$class] ?? class_basename($class);
    }

    /** آیا این مدل باید از ثبت مستثنا شود؟ */
    public static function isExcluded(string $class): bool
    {
        foreach ((array) config('activity-log.excluded_models', []) as $pattern) {
            // الگوی پیشوندی: 'App\Models\Chat\'
            if (str_ends_with($pattern, '\\')) {
                if (str_starts_with($class, $pattern)) {
                    return true;
                }

                continue;
            }
            if ($class === $pattern) {
                return true;
            }
        }

        return false;
    }

    /**
     * عنوانِ خواناى رکورد (برای نمایش در فهرست) — اولین ستونِ معنادار.
     *
     * عمداً از attributeهای «خام» خوانده می‌شود نه getAttribute: این متد روی
     * هر مدلِ سیستم اجرا می‌شود و نباید accessorـی را صدا بزند که ممکن است
     * کوئری/relation لود کند (کندی و N+1 سراسری).
     */
    public static function titleOf(Model $model): ?string
    {
        $raw = $model->getAttributes();

        foreach ([
            'order_code', 'code', 'title', 'name', 'subject', 'keyword',
            'slug', 'question', 'label', 'key',
        ] as $attr) {
            $value = $raw[$attr] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return Str::limit(trim($value), 120, '');
            }
        }

        // مدل‌های شخص‌محور (کاربر/مشتری/تکنسین)
        $full = trim(((string) ($raw['first_name'] ?? '')).' '.((string) ($raw['last_name'] ?? '')));
        if ($full !== '') {
            return $full;
        }

        $mobile = $raw['mobile'] ?? null;

        return is_string($mobile) && $mobile !== '' ? $mobile : null;
    }

    /** فهرست انواعِ رکوردی که در فایل‌های موجود دیده شده‌اند + برچسبشان. */
    public static function labelsForFilter(): array
    {
        $out = [];
        foreach (static::modelLabels() as $class => $label) {
            $out[$class] = $label;
        }
        asort($out);

        return $out;
    }
}
