<?php

namespace Modules\CRM\Enums;

/**
 * شش رویدادی که به اپِ تکنسین پوش می‌فرستند — جدولِ بخش ۴ سند نجوا.
 *
 * مقدارِ هر case عمداً همان `SmsTrigger` هم‌ارز است (آن‌جا که وجود دارد)،
 * تا در گزارشِ پنل بشود دو کانال را کنارِ هم گذاشت و پرسید «این رویداد از
 * کدام راه رفت».
 *
 * قالبِ عنوان و متن این‌جا فقط «پیش‌فرض» است؛ پنل ادمین بعداً می‌تواند
 * رویشان بنویسد.
 */
enum PushEvent: string
{
    case OrderAssigned = 'order_assigned_tech';
    case OrderStatusChanged = 'order_status_changed';
    case OrderReturned = 'tech_order_returned';
    case Announcement = 'announcement';
    case SlaDue = 'tech_sla_due';
    case OperatorMessage = 'operator_message';

    public function label(): string
    {
        return match ($this) {
            self::OrderAssigned => 'تخصیص سفارش جدید',
            self::OrderStatusChanged => 'تغییر وضعیت سفارش توسط ادمین',
            self::OrderReturned => 'سفارش برگشتی',
            self::Announcement => 'اطلاعیهٔ ادمین',
            self::SlaDue => 'سررسید مهلت ثبت وضعیت',
            self::OperatorMessage => 'پیام جدید کارشناس',
        };
    }

    public function defaultTitle(): string
    {
        return match ($this) {
            self::OrderAssigned => 'سفارش جدید',
            self::OrderStatusChanged => 'وضعیت سفارش تغییر کرد',
            self::OrderReturned => 'سفارش برگشتی',
            self::Announcement => 'اطلاعیهٔ جدید',
            self::SlaDue => 'مهلت ثبت وضعیت رسید',
            self::OperatorMessage => 'پیام جدید از پشتیبانی',
        };
    }

    public function defaultBody(): string
    {
        return match ($this) {
            self::OrderAssigned => '{customer_name} — {order_code} · {device_name}',
            self::OrderStatusChanged => '{order_code}: {status_label}',
            self::OrderReturned => '{order_code} منتظر تأیید یا رد شماست.',
            self::Announcement => '{title}',
            self::SlaDue => 'وضعیت {order_code} را همین حالا ثبت کنید.',
            self::OperatorMessage => '{excerpt}',
        };
    }

    /** ساعتِ ماندگاریِ اعلان روی سرورِ نجوا. */
    public function ttl(): int
    {
        // مهلت شش ساعته است چون بعد از آن خودِ پیام بی‌معنا می‌شود:
        // اعلانی که فردا برسد، مهلتی را یادآوری می‌کند که گذشته است.
        return $this === self::SlaDue ? 6 : 24;
    }

    /** مقصدِ کلیک — همیشه لینکِ کاملِ https روی دامنهٔ خودِ اپ. */
    public function clickUrl(array $vars = []): string
    {
        $base = rtrim((string) config('services.tech_app.url', 'https://tg.tamironline.com'), '/');

        $path = match ($this) {
            self::Announcement => '/announcements',
            self::OperatorMessage => '/messages',
            default => '/orders/'.($vars['order_id'] ?? ''),
        };

        return $base.rtrim($path, '/');
    }

    /** متغیرهای مجاز — کنارِ فیلدِ قالب در پنل ادمین نمایش داده می‌شوند. */
    public function variables(): array
    {
        return match ($this) {
            self::OrderAssigned => ['order_code', 'customer_name', 'device_name', 'technician_name'],
            self::OrderStatusChanged => ['order_code', 'status_label', 'technician_name'],
            self::OrderReturned, self::SlaDue => ['order_code', 'technician_name'],
            self::Announcement => ['title'],
            self::OperatorMessage => ['excerpt'],
        };
    }

    /**
     * جای‌گذاریِ متغیرها در یک قالب.
     *
     * متغیرِ نامشخص به رشتهٔ خالی تبدیل می‌شود نه اینکه `{order_code}` خام
     * روی گوشیِ تکنسین بنشیند.
     */
    public function render(string $template, array $vars): string
    {
        $replacements = [];
        foreach ($this->variables() as $name) {
            $replacements['{'.$name.'}'] = (string) ($vars[$name] ?? '');
        }

        return trim(preg_replace('/\s+/u', ' ', strtr($template, $replacements)));
    }

    public function title(array $vars = []): string
    {
        return $this->render($this->defaultTitle(), $vars);
    }

    public function body(array $vars = []): string
    {
        return $this->render($this->defaultBody(), $vars);
    }
}
