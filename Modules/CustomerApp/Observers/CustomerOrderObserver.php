<?php

namespace Modules\CustomerApp\Observers;

use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CustomerApp\Notifications\OrderCreatedNotification;
use Modules\CustomerApp\Notifications\OrderStatusChangedNotification;

/**
 * Observer مخصوص اپ مشتری — به Order events گوش می‌دهد و notification
 * مناسب را به مشتری ارسال می‌کند. کاملاً جدا از WP push observer در
 * مدل Order تا منطق هر کانال در ماژول خودش بماند.
 *
 * Notification ها از طریق Notifiable trait مشتری (روی Modules\CRM\Models\
 * Customer) در جدول notifications ذخیره می‌شوند و اپ آن‌ها را از
 * `GET /v1/customer/notifications` می‌خواند.
 */
class CustomerOrderObserver
{
    public function created(Order $order): void
    {
        if (! $this->shouldNotify($order)) {
            return;
        }
        $customer = $order->customer;
        if (! $customer) {
            return;
        }

        try {
            $customer->notify(new OrderCreatedNotification($order));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('customer_app.notify_failed', [
                'kind' => 'order_created',
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(Order $order): void
    {
        // فقط روی تغییر status علاقه داریم — تغییر فیلدهای دیگر بی‌اهمیت
        if (! $order->wasChanged('status')) {
            return;
        }
        if (! $this->shouldNotify($order)) {
            return;
        }

        $newStatus = $order->status;
        if (! $newStatus instanceof OrderStatus) {
            return;
        }
        $oldStatusRaw = $order->getOriginal('status');
        $oldStatus = is_string($oldStatusRaw) ? OrderStatus::tryFrom($oldStatusRaw) : null;

        $customer = $order->customer;
        if (! $customer) {
            return;
        }

        try {
            $customer->notify(new OrderStatusChangedNotification($order, $oldStatus, $newStatus));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('customer_app.notify_failed', [
                'kind' => 'order_status_changed',
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * شرایط اطلاع‌رسانی:
     *   - customer_id باشد (سفارش بدون مشتری در WP sync ممکن است رخ دهد)
     *   - is_lead نباشد (سفارش‌های لید notification نمی‌خواهند)
     *   - در CLI/queue/seeder نباشیم (تا backfillها spam نکنند)
     *   - WP inbound sync نباشد (suppress flag)
     */
    private function shouldNotify(Order $order): bool
    {
        if (! $order->customer_id) {
            return false;
        }
        if ($order->is_lead) {
            return false;
        }
        if (app()->bound('crm.suppress_outbound_push')) {
            return false; // در طول import از WP، notification spam نکن
        }
        if (app()->runningInConsole() && ! app()->bound('crm.wp_push.force')) {
            return false;
        }

        return true;
    }
}
