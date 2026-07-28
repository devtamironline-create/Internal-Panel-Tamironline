<?php

namespace Modules\CRM\Support;

use Modules\CRM\Models\CrmSetting;

/**
 * متنِ پیام‌های سیستمِ مهلت در اپ تکنسین.
 *
 * لحنِ این متن‌ها عمدی است: هدف «قفل‌کردن اپ» نیست، هدف این است که
 * تکنسین سریع تصمیم بگیرد. پس هیچ متنی تهدید یا سرزنش نمی‌کند؛ به‌جای
 * «تخلف کردی» می‌گوید «مشتری منتظر است» و همیشه کارِ بعدی را صریح
 * می‌گوید. تحقیقِ رفتاری ساده: پیامی که راهِ خروج را نشان می‌دهد سریع‌تر
 * به عمل ختم می‌شود تا پیامی که فقط خطا را اعلام می‌کند.
 *
 * همهٔ کلیدها از پنل ادمین قابل ویرایش‌اند (crm_settings: app_messages) و
 * ادغام key-by-key است: هر کلیدی که ادمین تغییر نداده، پیش‌فرض می‌ماند.
 *
 * ⚠️ کلیدها باید با DEFAULT_SLA.messages در mobile/src/lib/sla.ts یکی
 * باشند. اگر اپ کلیدی داشت که اینجا نیست، فرمِ ادمین آن را نشان نمی‌دهد
 * ولی اپ همچنان پیش‌فرضِ خودش را استفاده می‌کند (شکستنی نیست).
 */
final class AppMessages
{
    /** @var array<string, string> */
    public const DEFAULTS = [
        // ─── قفل اپ
        'lock_title' => 'چند سفارش منتظر تصمیم شماست',
        'lock_body' => 'برای ادامهٔ کار، وضعیت سفارش‌های زیر را مشخص کنید. این کار کمتر از یک دقیقه طول می‌کشد و مشتری را در بلاتکلیفی نگه نمی‌دارد.',
        'lock_cta' => 'تعیین وضعیت',
        'lock_single' => 'یک سفارش منتظر تعیین وضعیت است.',
        'lock_multiple' => ':count سفارش منتظر تعیین وضعیت هستند.',

        // ─── بنر هشدار (پیش از سررسید)
        'warning_title' => 'مهلت تعیین وضعیت نزدیک است',
        'warning_body' => 'تا :time دیگر فرصت دارید وضعیت این سفارش را ثبت کنید.',
        'overdue_title' => 'مهلت تعیین وضعیت گذشته',
        'overdue_body' => 'وضعیت این سفارش هنوز ثبت نشده. لطفاً همین حالا تعیین تکلیف کنید.',

        // ─── پیام اختصاصی هر وضعیت
        'overdue_new' => 'یک ساعت از تخصیص این سفارش گذشته. با مشتری تماس بگیرید و نتیجه را ثبت کنید.',
        'overdue_awaiting_coordination' => 'هماهنگی با مشتری هنوز انجام نشده. تماس بگیرید و زمان مراجعه را ثبت کنید.',
        'overdue_no_answer' => 'مشتری پاسخگو نبوده است. تا ۴۸ ساعت پس از تخصیص باید نتیجهٔ نهایی مشخص شود.',
        'overdue_coordinated' => 'زمان مراجعهٔ توافق‌شده فرا رسیده. مشخص کنید کار باز شده یا دستگاه به تعمیرگاه منتقل می‌شود.',
        'overdue_transit' => 'تاریخ تخمینی آماده‌شدن دستگاه گذشته. وضعیت جدید را ثبت کنید یا تخمین را به‌روز کنید.',
        'overdue_suspended' => 'این سفارش سه روز است معلق مانده. تعیین تکلیف کنید تا از کارتابل خارج شود.',
        'overdue_awaiting_part' => 'هفت روز از انتظار قطعه گذشته. وضعیت نهایی را مشخص کنید.',
        'overdue_awaiting_customer_approval' => 'بیش از ۲۴ ساعت از ارسال برای تأیید مشتری گذشته. نتیجه را ثبت کنید.',
        'overdue_repair_started' => 'پنج روز از شروع تعمیر گذشته. سفارش را نهایی کنید.',

        // ─── راهنمای گام‌به‌گام فاز هماهنگی
        'call_prompt_title' => 'نتیجهٔ تماس چه بود؟',
        'call_prompt_body' => 'پس از تماس با مشتری یکی از دو گزینه را انتخاب کنید تا سفارش در وضعیت درست قرار بگیرد.',
        'call_answered_label' => 'پاسخ داد — زمان مراجعه را ثبت می‌کنم',
        'call_no_answer_label' => 'پاسخ نداد — دوباره تماس می‌گیرم',
        'schedule_hint' => 'زمانی را ثبت کنید که مطمئنید می‌توانید مراجعه کنید؛ همین زمان مبنای مهلت بعدی شماست.',
        'estimate_hint' => 'تاریخ تخمینی آماده‌شدن را انتخاب کنید. حداکثر ۱۴ روز آینده قابل انتخاب است و همین تاریخ در پنل ثبت می‌شود.',

        // ─── بازبینی برگشتی
        'return_review_title' => 'بررسی سفارش برگشتی',
        'return_review_body' => 'پیش از ادامه، مشخص کنید خدمات قبلی واقعاً ایراد داشته یا مشکل تازه‌ای پیش آمده است.',
        'return_review_approve_label' => 'برگشتی تأیید می‌شود — ایراد از خدمات قبلی بوده',
        'return_review_reject_label' => 'برگشتی رد می‌شود — ایراد جدید است',
        'return_review_approved_note' => 'خدمات جدید بدون هزینه برای مشتری ثبت می‌شود. زمان تخمینی انجام کار را وارد کنید.',
        'return_review_rejected_note' => 'سفارش مانند یک سفارش جدید ادامه پیدا می‌کند و مراحل هماهنگی و قیمت‌گذاری عادی طی می‌شود.',
    ];

    private static ?array $memo = null;

    /** @return array<string, string> */
    public static function all(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $stored = CrmSetting::getJson('app_messages', []);
        if (! is_array($stored)) {
            $stored = [];
        }

        $out = self::DEFAULTS;
        foreach ($stored as $key => $value) {
            // کلیدهای ناشناخته هم نگه داشته می‌شوند: اپ ممکن است کلیدی
            // داشته باشد که هنوز در این فهرست نیامده.
            if (is_string($value) && trim($value) !== '') {
                $out[$key] = $value;
            }
        }

        return self::$memo = $out;
    }

    /** فقط کلیدهایی که ادمین بازنویسی کرده — برای نمایش در فرم. */
    public static function overrides(): array
    {
        $stored = CrmSetting::getJson('app_messages', []);

        return is_array($stored) ? $stored : [];
    }

    public static function save(array $values): void
    {
        $clean = [];
        foreach ($values as $key => $value) {
            $key = trim((string) $key);
            $value = trim((string) $value);
            if ($key === '' || $value === '') {
                continue; // خالی = برگرد به پیش‌فرض
            }
            $clean[$key] = mb_substr($value, 0, 500);
        }

        CrmSetting::setJson('app_messages', $clean);
        self::$memo = null;
    }

    public static function flush(): void
    {
        self::$memo = null;
    }
}
