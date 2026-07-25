<?php

namespace Modules\CustomerApp\Support;

use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Order;

/**
 * قالب‌های پیامِ اطلاع‌رسانیِ مشتری (نوتیفِ اپ + بله) — قابلِ مدیریت از پنل.
 *
 * ذخیره در CrmSetting با کلیدِ customer_notify_templates (JSON):
 *   { "<key>": {"enabled": bool, "title": "...", "body": "..."} , ... }
 * کلیدها: order_created + status_{value} برای هر وضعیت.
 *
 * placeholderها: {order_code} {status} {customer_name} {device} {brand} {technician}
 */
class NotifyTemplates
{
    public const SETTING_KEY = 'customer_notify_templates';

    /**
     * پیش‌فرض‌های حرفه‌ای — ادمین از پنل هر کدام را می‌تواند ویرایش/خاموش کند.
     *
     * @return array<string, array{enabled: bool, title: string, body: string}>
     */
    public static function defaults(): array
    {
        return [
            'order_created' => [
                'enabled' => true,
                'title' => '✅ سفارش شما ثبت شد',
                'body' => '{customer_name} عزیز، سفارش {device} شما با کد پیگیری {order_code} ثبت شد. کارشناسان ما به‌زودی برای هماهنگی با شما تماس می‌گیرند. 🌟',
            ],
            // تغییر به «جدید» عملاً همان لحظهٔ ثبت است — پیش‌فرض خاموش تا پیامِ تکراری نرود.
            'status_new' => [
                'enabled' => false,
                'title' => '📝 سفارش جدید',
                'body' => 'سفارش {order_code} ثبت شد.',
            ],
            'status_awaiting_coordination' => [
                'enabled' => true,
                'title' => '📞 در صف هماهنگی',
                'body' => 'سفارش {order_code} در صف هماهنگی قرار گرفت؛ به‌زودی برای تعیین زمان مراجعه با شما تماس می‌گیریم.',
            ],
            'status_no_answer' => [
                'enabled' => true,
                'title' => '☎️ موفق به تماس نشدیم',
                'body' => 'برای هماهنگی سفارش {order_code} تماس گرفتیم اما پاسخی دریافت نشد. همکاران ما دوباره تماس می‌گیرند؛ در صورت تمایل با پشتیبانی در ارتباط باشید.',
            ],
            'status_coordinated' => [
                'enabled' => true,
                'title' => '👨‍🔧 تکنسین شما انتخاب شد',
                'body' => 'هماهنگی سفارش {order_code} انجام شد و تکنسین {technician} برای خدمت‌رسانی به شما تعیین شد.',
            ],
            'status_repair_started' => [
                'enabled' => true,
                'title' => '🔧 تعمیر آغاز شد',
                'body' => 'تعمیر {device} {brand} شما آغاز شد. روندِ کار را از همین‌جا دنبال کنید. (سفارش {order_code})',
            ],
            'status_open' => [
                'enabled' => true,
                'title' => '🏭 انتقال به تعمیرگاه',
                'body' => '{device} شما برای بررسی دقیق‌تر به تعمیرگاه منتقل شد؛ رسیدِ انتقال برایتان ارسال می‌شود. (سفارش {order_code})',
            ],
            'status_awaiting_part' => [
                'enabled' => true,
                'title' => '⏳ در انتظار قطعه',
                'body' => 'برای ادامهٔ تعمیر {device} در انتظار تأمین قطعه هستیم؛ به‌محض آماده‌شدن اطلاع می‌دهیم. (سفارش {order_code})',
            ],
            'status_awaiting_customer_approval' => [
                'enabled' => true,
                'title' => '💬 منتظر تأیید شما هستیم',
                'body' => 'برای ادامهٔ سفارش {order_code} به تأیید شما نیاز داریم؛ لطفاً پیش‌فاکتور را بررسی و اعلام نظر کنید.',
            ],
            'status_suspended' => [
                'enabled' => true,
                'title' => '⏸ سفارش موقتاً متوقف شد',
                'body' => 'سفارش {order_code} موقتاً متوقف شد. برای جزئیات با پشتیبانی در تماس باشید.',
            ],
            'status_transit' => [
                'enabled' => true,
                'title' => '🚗 تکنسین در راه است',
                'body' => 'تکنسین {technician} در مسیرِ رسیدن به شماست؛ لطفاً در دسترس باشید. (سفارش {order_code})',
            ],
            'status_completed' => [
                'enabled' => true,
                'title' => '🎉 سفارش شما انجام شد',
                'body' => 'تعمیر {device} شما با موفقیت انجام شد! خوشحال می‌شویم تجربه‌تان را ثبت کنید ⭐ (سفارش {order_code})',
            ],
            'status_cancelled' => [
                'enabled' => true,
                'title' => '❌ سفارش لغو شد',
                'body' => 'سفارش {order_code} لغو شد. اگر ابهامی دارید، پشتیبانی پاسخگوی شماست.',
            ],
            'status_declined' => [
                'enabled' => true,
                'title' => '⚠️ سفارش رد شد',
                'body' => 'سفارش {order_code} توسط تکنسین رد شد؛ همکاران ما برای تعیین تکلیف با شما تماس می‌گیرند.',
            ],
            'status_returned' => [
                'enabled' => true,
                'title' => '🛡 در روند گارانتی',
                'body' => 'سفارش {order_code} در روند گارانتی قرار گرفت و در اسرع وقت رسیدگی می‌شود.',
            ],
        ];
    }

    /**
     * همهٔ قالب‌ها — overrideهای ذخیره‌شده روی پیش‌فرض‌ها merge می‌شوند.
     *
     * @return array<string, array{enabled: bool, title: string, body: string}>
     */
    /** memo برای یک request (چند نوتیفیکیشن/چند فراخوانی). */
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $defaults = self::defaults();
        $saved = CrmSetting::getJson(self::SETTING_KEY, []);
        if (! is_array($saved)) {
            return self::$cache = $defaults;
        }

        foreach ($defaults as $key => $def) {
            $row = $saved[$key] ?? null;
            if (is_array($row)) {
                $defaults[$key] = [
                    'enabled' => array_key_exists('enabled', $row) ? (bool) $row['enabled'] : $def['enabled'],
                    'title' => trim((string) ($row['title'] ?? '')) !== '' ? (string) $row['title'] : $def['title'],
                    'body' => trim((string) ($row['body'] ?? '')) !== '' ? (string) $row['body'] : $def['body'],
                ];
            }
        }

        return self::$cache = $defaults;
    }

    /**
     * قالبِ renderشده برای یک رویداد — null یعنی «این پیام خاموش است».
     *
     * @return array{title: string, body: string}|null
     */
    public static function resolve(string $key, Order $order): ?array
    {
        $tpl = self::all()[$key] ?? null;
        if ($tpl === null || ! $tpl['enabled']) {
            return null;
        }

        return [
            'title' => self::fill($tpl['title'], $order),
            'body' => self::fill($tpl['body'], $order),
        ];
    }

    /** کلیدِ قالبِ تغییرِ وضعیت. */
    public static function statusKey(OrderStatus $status): string
    {
        return 'status_'.$status->value;
    }

    /** جایگزینیِ placeholderها + پاک‌سازیِ باقی‌مانده‌ها. */
    private static function fill(string $template, Order $order): string
    {
        $status = $order->status instanceof OrderStatus ? $order->status : null;

        $map = [
            '{order_code}' => (string) $order->order_code,
            '{status}' => $status?->label() ?? '',
            '{customer_name}' => trim((string) ($order->customer_name ?: ($order->customer->display_name ?? ''))) ?: 'مشتری',
            '{device}' => (string) ($order->device->name ?? 'دستگاه'),
            '{brand}' => (string) ($order->brand->name ?? ''),
            '{technician}' => trim((string) ($order->technician->full_name ?? '')) ?: 'ما',
        ];

        $out = strtr($template, $map);

        // فشرده‌سازیِ فاصله‌های ناشی از placeholderهای خالی
        return trim(preg_replace('/[ \t]{2,}/u', ' ', $out) ?? $out);
    }
}
