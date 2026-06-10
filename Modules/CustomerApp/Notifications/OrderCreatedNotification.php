<?php

namespace Modules\CustomerApp\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\CRM\Models\Order;

/**
 * Notification هنگام ثبت سفارش جدید — به مشتری اطلاع می‌دهد سفارشش ثبت شد.
 *
 * فقط کانال database — در `notifications` لاراول ذخیره می‌شود و
 * `GET /v1/customer/notifications` آن را به اپ موبایل می‌دهد.
 * SMS از طریق سیستم موجود Kavenegar جدا فرستاده می‌شود (در آینده می‌توان
 * این Notification را به کانال `sms` هم گسترش داد).
 */
class OrderCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * شکل ذخیره در DB — توسط NotificationController.shortType()/shape خوانده می‌شود.
     *
     * @return array<string, mixed>
     */
    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'سفارش شما ثبت شد',
            'body' => sprintf(
                'سفارش با کد پیگیری %s ثبت شد. به‌زودی برای هماهنگی با شما تماس می‌گیریم.',
                $this->order->order_code
            ),
            'payload' => [
                'order_id' => (int) $this->order->id,
                'tracking_code' => $this->order->order_code,
            ],
        ];
    }
}
