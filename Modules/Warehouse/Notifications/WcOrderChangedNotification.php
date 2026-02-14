<?php

namespace Modules\Warehouse\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Warehouse\Models\WarehouseOrder;

class WcOrderChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected WarehouseOrder $order,
        protected string $wcOldStatus,
        protected string $wcNewStatus,
        protected string $panelStatus,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $statusLabels = [
            'processing' => 'در حال پردازش',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
            'refunded' => 'مسترد شده',
            'failed' => 'ناموفق',
            'on-hold' => 'در انتظار',
            'pending' => 'در انتظار پرداخت',
            'bslm-preparation' => 'آماده‌سازی باسلام',
        ];

        $newLabel = $statusLabels[$this->wcNewStatus] ?? $this->wcNewStatus;
        $panelLabel = WarehouseOrder::statusLabels()[$this->panelStatus] ?? $this->panelStatus;

        return [
            'type' => 'wc_order_changed',
            'title' => "تغییر وضعیت سفارش {$this->order->order_number}",
            'body' => "وضعیت در سایت: {$newLabel} | وضعیت در پنل: {$panelLabel}",
            'url' => route('warehouse.show', $this->order->id),
            'icon' => 'warning',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'wc_new_status' => $this->wcNewStatus,
            'panel_status' => $this->panelStatus,
        ];
    }
}
