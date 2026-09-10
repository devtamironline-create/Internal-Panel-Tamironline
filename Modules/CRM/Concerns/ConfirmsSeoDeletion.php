<?php

namespace Modules\CRM\Concerns;

use Illuminate\Http\Request;

/**
 * حذفِ محتوای سایت نباید «یک‌کلیک» باشد: کاربر باید اسلاگ/مسیرِ دقیقِ
 * موجودیت را تایپ کند. این trait همان تأیید را سمتِ سرور هم اجبار می‌کند
 * (JS قابلِ دور زدن است).
 */
trait ConfirmsSeoDeletion
{
    /**
     * اگر مقدارِ تایپ‌شده با مقدارِ انتظاری یکی نبود true برمی‌گرداند (یعنی
     * «تأیید نشده»). ارقامِ فارسی/عربی به لاتین تبدیل و فاصله‌ها trim می‌شوند.
     */
    protected function seoDeletionNotConfirmed(Request $request, string $expected): bool
    {
        $typed = trim($this->normalizeConfirmValue((string) $request->input('confirm_slug', '')));
        $expected = trim($this->normalizeConfirmValue($expected));

        return $typed === '' || $typed !== $expected;
    }

    private function normalizeConfirmValue(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
