<?php

namespace Modules\CRM\Services\Najva;

/**
 * نتیجهٔ یک فراخوانیِ ارسال — بدونِ استثنا.
 *
 * سرویسِ ارسال هیچ‌وقت throw نمی‌کند (همان قرارداد `KavenegarService`):
 * فرستادنِ اعلان نباید تراکنشِ سفارش را بشکند. پس همه‌چیز — موفق، ناموفق،
 * باطل، خطای شبکه — در همین شیء برمی‌گردد و صدازننده تصمیم می‌گیرد.
 */
final class NajvaResult
{
    /**
     * @param  list<string>  $sent  توکن‌هایی که نجوا پذیرفت
     * @param  list<string>  $invalid  توکن‌هایی که باطل اعلام شدند (و باطل شدند)
     * @param  list<string>  $failed  توکن‌هایی که اصلاً به مقصد نرسیدند
     * @param  list<string>  $requestIds  برای پیگیریِ گزارش و لغوِ زمان‌بندی
     */
    public function __construct(
        public readonly array $sent = [],
        public readonly array $invalid = [],
        public readonly array $failed = [],
        public readonly array $requestIds = [],
        public readonly int $cost = 0,
        public readonly ?int $errorCode = null,
        public readonly ?string $errorMessage = null,
    ) {}

    /**
     * ارسال به «همه» موفق بود؟ توکنِ باطل شکست نیست — پاک‌سازی است.
     *
     * `errorMessage` هم سنجیده می‌شود نه فقط `errorCode`: خطای «تنظیم
     * نشده» و خطای شبکه کدِ HTTP ندارند، و بدونِ این شرط «هیچ چیز ارسال
     * نشد» را موفق گزارش می‌کردیم.
     */
    public function ok(): bool
    {
        return $this->errorCode === null
            && $this->errorMessage === null
            && $this->failed === [];
    }

    /** هیچ توکنی نبود، یا سرویس تنظیم نشده بود. */
    public function isEmpty(): bool
    {
        return $this->sent === [] && $this->invalid === [] && $this->failed === [];
    }

    /**
     * خطایی که ادمین باید فوراً ببیند: کلیدِ نامعتبر، IPِ ثبت‌نشده، یا
     * تمام‌شدنِ اعتبار. بقیهٔ خطاها باگِ ماست و در لاگ می‌ماند.
     */
    public function needsAdminAlarm(): bool
    {
        return in_array($this->errorCode, [403, 416, 418], true);
    }

    public static function notConfigured(): self
    {
        return new self(errorMessage: 'کلید API یا شناسهٔ سایت نجوا تنظیم نشده است.');
    }
}
