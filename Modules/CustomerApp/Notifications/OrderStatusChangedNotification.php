<?php

namespace Modules\CustomerApp\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CustomerApp\Support\OrderStatusMapper;

/**
 * Notification هنگام تغییر وضعیت سفارش — به مشتری از طریق
 * `GET /v1/customer/notifications` اطلاع می‌دهد.
 *
 * فرانت با خواندن payload.new_status می‌تواند روی notification deep-link به
 * صفحه‌ی سفارش بزند، یا UI خاص هر status را اعمال کند.
 */
class OrderStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public ?OrderStatus $oldStatus,
        public OrderStatus $newStatus,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        // قالبِ قابلِ‌مدیریت از پنل — خاموش بودنِ این وضعیت یعنی هیچ پیامی نرود.
        if ($this->template() === null) {
            return [];
        }

        $channels = ['database'];

        // بله: از طریقِ چتِ ربات (bale_user_id) یا سفیر (شمارهٔ موبایل) —
        // BaleChannel خودش بهترین مسیرِ در دسترس را انتخاب می‌کند.
        if (\Modules\CustomerApp\Channels\BaleChannel::configuredFor($notifiable)) {
            $channels[] = \Modules\CustomerApp\Channels\BaleChannel::class;
        }

        return $channels;
    }

    /** متنِ پیامِ بله — همان قالبِ پنل. */
    public function toBale($notifiable): string
    {
        $tpl = $this->template();

        return $tpl ? $tpl['title']."\n".$tpl['body'] : '';
    }

    /** @return array{title: string, body: string}|null */
    private function template(): ?array
    {
        return \Modules\CustomerApp\Support\NotifyTemplates::resolve(
            \Modules\CustomerApp\Support\NotifyTemplates::statusKey($this->newStatus),
            $this->order,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase($notifiable): array
    {
        $tpl = $this->template();
        $title = $tpl['title'] ?? $this->titleFor($this->newStatus);
        $body = $tpl['body'] ?? sprintf('سفارش %s: %s', $this->order->order_code, $this->newStatus->label());

        return [
            'title' => $title,
            'body' => $body,
            'payload' => [
                'order_id' => (int) $this->order->id,
                'tracking_code' => $this->order->order_code,
                'old_status' => $this->oldStatus ? OrderStatusMapper::toMobileString($this->oldStatus) : null,
                'new_status' => OrderStatusMapper::toMobileString($this->newStatus),
                'new_status_label' => $this->newStatus->label(),
                'is_terminal' => OrderStatusMapper::isTerminal($this->newStatus),
            ],
        ];
    }

    /**
     * تیتر مناسب برای هر وضعیت (پیام شفاف‌تر از label خام).
     */
    private function titleFor(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Coordinated => 'تکنسین انتخاب شد',
            OrderStatus::Open => 'زمان مراجعه ست شد',
            OrderStatus::Transit => 'تکنسین در راه است',
            OrderStatus::Completed => 'سفارش انجام شد — لطفاً نظر دهید',
            OrderStatus::Cancelled => 'سفارش لغو شد',
            OrderStatus::Declined => 'سفارش رد شد',
            OrderStatus::Suspended => 'سفارش معلق شد',
            OrderStatus::Returned => 'سفارش برگشت‌خورده است',
            default => 'وضعیت سفارش تغییر کرد',
        };
    }
}
