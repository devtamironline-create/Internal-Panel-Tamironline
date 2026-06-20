<?php

namespace Modules\CustomerApp\Support;

use Modules\CRM\Models\Order;

/**
 * پیوست‌های (عکس‌های) یک سفارش برای پاسخِ API مشتری.
 *
 * منبع فعلی: ستون‌های موجودِ سفارش —
 *   - device_img1         : عکسِ پس‌از‌تعمیرِ تکنسین (هنگام بستن سفارش اجباری است)
 *   - device_image_input  : عکسِ legacy/مهاجرت‌شده از سیستم قبلی
 *
 * URLها همیشه «مطلق» برگردانده می‌شوند: مقادیر مطلق (http/https یا مسیرِ ریشه)
 * دست‌نخورده می‌مانند و مسیرهای نسبیِ داخلِ public disk از طریق storage proxy
 * (route `storage.proxy`) به URL مطلق تبدیل می‌شوند — همان منطقی که پنل در
 * صفحه‌ی سفارش استفاده می‌کند.
 *
 * سفارش‌هایی که هیچ عکسی ندارند → آرایه‌ی خالی، تا فرانت fallback (آیکن دسته)
 * را نشان دهد و خطا ندهد.
 */
final class OrderAttachments
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function for(Order $order): array
    {
        $sources = [
            ['value' => $order->device_img1, 'uploaded_by' => 'technician'],
            ['value' => $order->device_image_input, 'uploaded_by' => 'technician'],
        ];

        $createdAt = optional($order->completed_at ?? $order->updated_at ?? $order->created_at)
            ->utc()->toIso8601String();

        $out = [];
        $seen = [];
        $index = 0;

        foreach ($sources as $src) {
            $raw = trim((string) ($src['value'] ?? ''));
            if ($raw === '') {
                continue;
            }

            $url = self::resolveUrl($raw);
            if ($url === null || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $index++;

            $out[] = [
                'id' => (int) $order->id * 100 + $index,
                'url' => $url,
                'thumb_url' => $url,
                'type' => 'image',
                'uploaded_by' => $src['uploaded_by'],
                'created_at' => $createdAt,
            ];
        }

        return $out;
    }

    /**
     * مسیر/URL ذخیره‌شده را به URL مطلق تبدیل می‌کند.
     */
    private static function resolveUrl(string $img): ?string
    {
        $isAbsolute = (bool) preg_match('#^(https?:)?//#', $img) || str_starts_with($img, '/');
        if ($isAbsolute) {
            return $img;
        }

        return storage_url(ltrim($img, '/'));
    }
}
