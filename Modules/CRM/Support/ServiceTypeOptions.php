<?php

namespace Modules\CRM\Support;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\ServiceType;

/**
 * فهرستِ «نوع خدمت» از جدولِ crm_service_types.
 *
 * تا الان این فهرست در سه جا hardcode شده بود (فرم تکنسین، نمایش
 * تکنسین، و قوانین اعتبارسنجی) در حالی که ادمین می‌تواند از پنل نوع
 * جدید اضافه کند. نتیجه‌اش این بود که نوع جدید هرگز روی پروفایل تکنسین
 * قابل تیک‌زدن نبود و سفارشِ آن نوع برای همهٔ تکنسین‌ها رد می‌شد.
 *
 * اگر جدول در دسترس نباشد (نصب تازه، قبل از migration) به همان سه
 * مقدار قدیمی برمی‌گردیم تا پنل نشکند.
 */
final class ServiceTypeOptions
{
    private const FALLBACK = [
        'repair' => 'تعمیر',
        'service' => 'سرویس',
        'install' => 'نصب',
    ];

    private static ?array $memo = null;

    /** @return array<string, string> slug => name */
    public static function all(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        try {
            if (! Schema::hasTable('crm_service_types')) {
                return self::$memo = self::FALLBACK;
            }

            $rows = ServiceType::query()->active()->ordered()->pluck('name', 'slug')->all();
        } catch (\Throwable) {
            return self::$memo = self::FALLBACK;
        }

        return self::$memo = ! empty($rows) ? $rows : self::FALLBACK;
    }

    /** @return array<int, string> */
    public static function slugs(): array
    {
        return array_keys(self::all());
    }

    public static function label(?string $slug): ?string
    {
        if (! $slug) {
            return null;
        }

        return self::all()[$slug] ?? $slug;
    }

    /** پاک‌کردن حافظه — برای تست‌ها. */
    public static function flush(): void
    {
        self::$memo = null;
    }
}
