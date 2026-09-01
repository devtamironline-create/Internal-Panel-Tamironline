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

            // خوشهٔ کاریِ غیرنهایی — کاملاً به هم وصل است تا تکنسین بتواند
            // آزادانه به عقب برگردد (تصمیمِ ۱۴۰۵/۰۵/۲۸): مثلاً پس از «در
            // انتظار مشتری»، اگر مشتری تأیید کرد، دوباره «هماهنگ شده» شود.
            // مقصدهای نهایی (Completed/Transit/Cancelled/Declined) و NoAnswer
            // به این خوشه اضافه می‌شوند اما خودشان نهایی/فازِ تماس‌اند.
            self::Coordinated => [
                self::AwaitingPart, self::AwaitingCustomerApproval, self::Open,
                self::Suspended, self::NoAnswer,
                self::Transit, self::Completed, self::Cancelled, self::Declined,
            ],

            // انتقال به تعمیرگاه — برگشت به هماهنگی/انتظار مجاز است.
            self::Open => [
                self::Coordinated, self::AwaitingPart,
                self::AwaitingCustomerApproval, self::Suspended,
                self::Transit, self::Completed, self::Cancelled, self::Declined,
            ],

            // معلق: خوشهٔ کاری + امکانِ ردِ سفارش توسط تکنسین.
            self::Suspended => [
                self::Coordinated, self::AwaitingPart,
                self::AwaitingCustomerApproval, self::Open,
                self::Transit, self::Completed, self::Cancelled, self::Declined,
            ],

            // حالت‌های انتظار: خوشهٔ کاری + ایاب و ذهاب و ردِ سفارش.
            self::AwaitingPart,
            self::AwaitingCustomerApproval => [
                self::Coordinated, self::AwaitingPart, self::AwaitingCustomerApproval,
                self::Open, self::Suspended,
                self::Transit, self::Completed, self::Cancelled, self::Declined,
            ],

            // برگشتی گارانتی: کارشناسی (تأیید → هماهنگ‌شده، رد → تکمیل‌شده)،
            // یا لغو در مواردِ خاص.
            self::Returned => [self::Coordinated, self::Completed, self::Cancelled],

            // رد شده (تکنسین): ادمین می‌تواند به «لغو» تبدیل کند.
            self::Declined => [self::Cancelled],

            // وضعیت‌های نهایی محض — فقط با «بازگشت سفارش» قابل خروج.
            self::Cancelled,
            self::Completed,
            self::Transit => [],
        };
    }

    /**
     * وضعیت‌هایی که «تکنسین» مجازِ تنظیمِ آن‌هاست: گذارِ مرحله‌ایِ معتبر از
     * وضعیتِ فعلی (allowedTransitions) ∩ نقش‌های مجازِ تکنسین. لغو (Cancelled)
     * و وضعیت‌های فازِ ثبت/هماهنگیِ خودکار (New/AwaitingCoordination/NoAnswer)
     * دستیِ تکنسین نیستند — NoAnswer فقط از «نتیجهٔ تماس» ست می‌شود.
     *
     * @return array<int, self>
     */
    public function technicianTransitions(): array
    {
        $whitelist = [
            self::Coordinated, self::Open,
            self::AwaitingPart, self::AwaitingCustomerApproval,
            self::Suspended, self::Completed, self::Declined, self::Transit,
        ];

        return array_values(array_filter(
            $this->allowedTransitions(),
            fn (self $s) => in_array($s, $whitelist, true)
        ));
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
     * مقادیرِ وضعیت‌های «تسویه‌شده» — سفارشِ بسته که تصمیمی از تکنسین
     * نمی‌خواهد. مرجعِ مشترکِ actionable_count در sync و فیلترِ
     * ?actionable=1 در لیستِ سفارش‌ها؛ دو مصرف‌کننده نباید از هم جدا شوند.
     *
     * @return list<string>
     */
    public static function settledValues(): array
    {
        return array_values(array_map(
            fn (self $s) => $s->value,
            array_filter(self::cases(), fn (self $s) => $s->isFinal())
        ));
    }

    /**
     * آیا در این وضعیت، تکنسین می‌تواند «زمانِ مراجعه» را تنظیم/تغییر/پاک کند؟
     * فقط در فازِ هماهنگی معنا دارد (جدید/در انتظار هماهنگی/پاسخگو‌نیست/هماهنگ‌شده).
     * پس از خروج از این فاز (باز/شروع تعمیر/انتظار قطعه/… و وضعیت‌های نهایی) قفل است.
     */
    public function allowsVisitScheduling(): bool
    {
        return match ($this) {
            // فازِ هماهنگی + خوشهٔ کاریِ غیرنهایی — تا تکنسین بتواند در طول
            // کار هم زمانِ مراجعه را دوباره تنظیم کند و «دوباره هماهنگ» شود
            // (تصمیمِ ۱۴۰۵/۰۵/۲۸). وضعیت‌های نهایی همچنان قفل‌اند.
            self::New, self::AwaitingCoordination, self::NoAnswer, self::Coordinated,
            self::Open, self::AwaitingPart,
            self::AwaitingCustomerApproval, self::Suspended => true,
            default => false,
        };
    }

    /**
     * آیا این وضعیت در «فازِ تماس/هماهنگی» است؟ (پیش از دیدنِ دستگاه.)
     * جدا از allowsVisitScheduling نگه داشته می‌شود چون تنظیمِ زمانِ مراجعه
     * حالا در طولِ کار هم مجاز است، ولی «فازِ هماهنگی» مفهومِ ثابت دارد.
     */
    public function isCoordinationPhase(): bool
    {
        return match ($this) {
            self::New, self::AwaitingCoordination, self::NoAnswer, self::Coordinated => true,
            default => false,
        };
    }

    /**
     * آیا در این وضعیت می‌توان برای سفارش پیش‌فاکتور صادر کرد؟
     *
     * پیش‌فاکتور فقط «پس از هماهنگی و پیش از بسته‌شدن» معنا دارد: در فازِ
     * تماس/هماهنگی هنوز دستگاه دیده نشده، و بعد از بسته‌شدن سفارش، سندِ
     * مالی فاکتور است نه پیش‌فاکتور. «هماهنگ‌شده» خودش هنوز فازِ هماهنگی
     * است — پیش‌فاکتور از «شروع تعمیر» به بعد باز می‌شود.
     *
     * تنها مرجعِ این قاعده — هم API (گیتِ ۴۲۲) و هم ریسورس‌ها
     * (can_create_proforma) از همین می‌خوانند.
     */
    public function allowsProforma(): bool
    {
        return ! $this->isCoordinationPhase() && ! $this->isFinal();
    }

    /**
     * گروهِ گزارشیِ سه‌گانه — برای دسته‌بندیِ ساده‌ترِ تب‌ها و گزارش‌ها:
     * in_progress (در حال انجام) | waiting (در انتظار) | finished (پایان یافته).
     */
    public function group(): string
    {
        return match ($this) {
            self::Coordinated => 'in_progress',

            self::New, self::AwaitingCoordination, self::NoAnswer,
            self::Suspended, self::Open, self::AwaitingPart,
            self::AwaitingCustomerApproval => 'waiting',

            self::Completed, self::Transit, self::Cancelled,
            self::Declined, self::Returned => 'finished',
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
