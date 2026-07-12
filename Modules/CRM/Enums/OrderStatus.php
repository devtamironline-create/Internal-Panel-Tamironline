<?php

namespace Modules\CRM\Enums;

/**
 * وضعیت سفارش — هم‌تراز با CRM وردپرسی (libs/order.php).
 *
 * در WP وضعیت در postmeta `status` به‌صورت عددی ذخیره می‌شود:
 *   0=جدید، 1=هماهنگ شده، 2=باز شده، 3=معلق، 4=کنسل شده،
 *   5=انجام کار، 10=ایاب و ذهاب، 100=رد سفارش.
 *
 * در لاراول از value های string (نام انگلیسی) استفاده می‌کنیم تا
 * در DB و کد خواناتر باشد، ولی نگاشت دوطرفه با کد عددی WP حفظ می‌شود
 * (متدهای wpCode/fromWpCode).
 *
 * Returned (11) در WP موجود نیست؛ اضافه‌ست برای پنل لاراول
 * به‌خاطر فرق معنایی با Transit/«ایاب و ذهاب».
 */
enum OrderStatus: string
{
    case New = 'new';                 // WP 0   — جدید
    case Coordinated = 'coordinated'; // WP 1   — هماهنگ شده
    case Open = 'open';               // WP 2   — باز شده (انتقال به تعمیرگاه)
    case Suspended = 'suspended';     // WP 3   — معلق
    case Cancelled = 'cancelled';     // WP 4   — لغو شده (فقط ادمین)
    case Completed = 'completed';     // WP 5   — انجام کار
    case Transit = 'transit';         // WP 10  — ایاب و ذهاب
    case Returned = 'returned';       // WP 11  — برگشتی گارانتی (لاراول‌اِسپِسیفیک)
    case Declined = 'declined';       // WP 100 — رد شده (توسط تکنسین)

    // ─── وضعیت‌های جدیدِ پنل (WP این‌ها را ندارد؛ wpCode امنِ نزدیک می‌گیرند) ───
    case AwaitingCoordination = 'awaiting_coordination';       // در انتظار هماهنگی با مشتری
    case NoAnswer = 'no_answer';                               // مشتری پاسخگو نیست
    case RepairStarted = 'repair_started';                     // شروع تعمیر (گروهِ «در حال انجام»)
    case AwaitingPart = 'awaiting_part';                       // در انتظار قطعه
    case AwaitingCustomerApproval = 'awaiting_customer_approval'; // در انتظار تأیید مشتری

    public function label(): string
    {
        return match ($this) {
            self::New => 'جدید',
            self::Coordinated => 'هماهنگ شده',
            self::Open => 'باز شده',
            self::Suspended => 'معلق',
            self::Cancelled => 'کنسل شده',
            self::Completed => 'انجام کار',
            self::Transit => 'ایاب و ذهاب',
            self::Returned => 'برگشتی گارانتی',
            self::Declined => 'رد شده',
            self::AwaitingCoordination => 'در انتظار هماهنگی',
            self::NoAnswer => 'مشتری پاسخگو نیست',
            self::RepairStarted => 'شروع تعمیر',
            self::AwaitingPart => 'در انتظار قطعه',
            self::AwaitingCustomerApproval => 'در انتظار تأیید مشتری',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::New => 'bg-gray-100 text-gray-800',
            self::Coordinated => 'bg-blue-100 text-blue-800',
            self::Open => 'bg-indigo-100 text-indigo-800',
            self::Suspended => 'bg-yellow-100 text-yellow-800',
            self::Cancelled => 'bg-red-100 text-red-800',
            self::Completed => 'bg-green-100 text-green-800',
            self::Transit => 'bg-amber-100 text-amber-800',
            self::Returned => 'bg-orange-100 text-orange-800',
            self::Declined => 'bg-red-200 text-red-900',
            self::AwaitingCoordination => 'bg-cyan-100 text-cyan-800',
            self::NoAnswer => 'bg-rose-100 text-rose-800',
            self::RepairStarted => 'bg-purple-100 text-purple-800',
            self::AwaitingPart => 'bg-teal-100 text-teal-800',
            self::AwaitingCustomerApproval => 'bg-lime-100 text-lime-800',
        };
    }

    /** نگاشت enum به کد عددی متناظر در WP. */
    public function wpCode(): int
    {
        return match ($this) {
            self::New => 0,
            self::Coordinated => 1,
            self::Open => 2,
            self::Suspended => 3,
            self::Cancelled => 4,
            self::Completed => 5,
            self::Transit => 10,
            self::Returned => 11,
            self::Declined => 100,
            // وضعیت‌های جدید در WP کدِ اختصاصی ندارند؛ به نزدیک‌ترین کدِ معنایی
            // نگاشت می‌شوند تا پوش به WP هرگز کرش نکند (WP فعلاً اولویت نیست).
            self::AwaitingCoordination => 0,   // مثلِ «جدید»
            self::NoAnswer => 3,               // مثلِ «معلق»
            self::RepairStarted => 2,          // مثلِ «باز شده / در حال انجام»
            self::AwaitingPart => 3,           // مثلِ «معلق»
            self::AwaitingCustomerApproval => 3, // مثلِ «معلق»
        };
    }

    /** ساخت enum از کد عددی WP (یا رشتهٔ عددی). */
    public static function fromWpCode(int|string|null $code): ?self
    {
        if ($code === null || $code === '') {
            return null;
        }

        return match ((int) $code) {
            0 => self::New,
            1 => self::Coordinated,
            2 => self::Open,
            3 => self::Suspended,
            4 => self::Cancelled,
            5 => self::Completed,
            10 => self::Transit,
            11 => self::Returned,
            100 => self::Declined,
            default => null,
        };
    }

    /** لیست گزینه‌ها برای dropdownها. */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * گذارهای وضعیت مجاز از وضعیت فعلی — هم‌ارز show_order.php در WP
     * (خط 532): اگر status ∈ {4,5,10} و negative_invoice != 1، فرم
     * dontEdit می‌شود؛ یعنی وضعیت‌های نهایی Cancelled/Completed/Transit
     * قفل می‌شوند و فقط با «بازگشت سفارش» می‌توان از آن‌ها خارج شد.
     *
     * در فرم WP رادیوها فقط 1،2،3،4،5 را نشان می‌دهند (یعنی از هر
     * وضعیت قابل ویرایش، می‌توان به Coordinated/Open/Suspended/
     * Cancelled/Completed رفت).
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            // ثبتِ اولیه → واردِ فرآیندِ هماهنگی/تخصیص می‌شود.
            self::New => [
                self::AwaitingCoordination, self::Coordinated, self::NoAnswer,
                self::Cancelled, self::Declined,
            ],

            // در انتظار هماهنگی: نتیجهٔ تماس ثبت می‌شود.
            self::AwaitingCoordination => [
                self::Coordinated, self::NoAnswer, self::Suspended,
                self::Cancelled, self::Declined,
            ],

            // مشتری پاسخگو نیست: تماسِ مجدد یا تعلیق.
            self::NoAnswer => [
                self::AwaitingCoordination, self::Coordinated, self::Suspended,
                self::Cancelled, self::Declined,
            ],

            // هماهنگ شده: تکنسین در محل — شروعِ کار یا حالت‌های انتظار.
            self::Coordinated => [
                self::RepairStarted, self::Open, self::AwaitingPart,
                self::AwaitingCustomerApproval, self::Suspended, self::NoAnswer,
                self::Transit, self::Completed, self::Cancelled,
            ],

            // شروع تعمیر (در حال انجام).
            self::RepairStarted => [
                self::Open, self::AwaitingPart, self::AwaitingCustomerApproval,
                self::Suspended, self::Transit, self::Completed, self::Cancelled,
            ],

            // انتقال به تعمیرگاه.
            self::Open => [
                self::RepairStarted, self::AwaitingPart, self::Suspended,
                self::Transit, self::Completed, self::Cancelled,
            ],

            // معلق / حالت‌های انتظار.
            self::Suspended,
            self::AwaitingPart,
            self::AwaitingCustomerApproval => [
                self::Coordinated, self::RepairStarted, self::Open,
                self::AwaitingPart, self::AwaitingCustomerApproval,
                self::Completed, self::Cancelled,
            ],

            // برگشتی گارانتی: کارشناسی (تأیید → هماهنگ‌شده، رد → لغو).
            self::Returned => [self::Coordinated, self::Cancelled],

            // رد شده (تکنسین): ادمین می‌تواند به «لغو» تبدیل کند.
            self::Declined => [self::Cancelled],

            // وضعیت‌های نهایی محض — فقط با «بازگشت سفارش» قابل خروج.
            self::Cancelled,
            self::Completed,
            self::Transit => [],
        };
    }

    /**
     * آیا این وضعیت نهایی است؟ explicitly از allowedTransitions جدا
     * می‌کنیم — Declined با وجود داشتن transition به Cancelled، همچنان
     * «نهایی» محسوب می‌شود (یعنی در UI به‌عنوان قفل/return-target ظاهر
     * می‌شود).
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::Cancelled, self::Completed, self::Transit, self::Declined => true,
            default => false,
        };
    }

    /**
     * گروهِ گزارشیِ سه‌گانه — برای دسته‌بندیِ ساده‌ترِ تب‌ها و گزارش‌ها:
     * in_progress (در حال انجام) | waiting (در انتظار) | finished (پایان یافته).
     */
    public function group(): string
    {
        return match ($this) {
            self::Coordinated, self::RepairStarted => 'in_progress',

            self::New, self::AwaitingCoordination, self::NoAnswer,
            self::Suspended, self::Open, self::AwaitingPart,
            self::AwaitingCustomerApproval, self::Returned => 'waiting',

            self::Completed, self::Transit, self::Cancelled, self::Declined => 'finished',
        };
    }

    /** برچسبِ فارسیِ گروه‌ها. */
    public static function groupLabels(): array
    {
        return [
            'in_progress' => 'در حال انجام',
            'waiting' => 'در انتظار',
            'finished' => 'پایان یافته',
        ];
    }
}
