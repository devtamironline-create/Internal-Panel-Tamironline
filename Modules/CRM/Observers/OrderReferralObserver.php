<?php

namespace Modules\CRM\Observers;

use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CRM\Services\ReferralService;

/**
 * وقتی وضعیتِ سفارش به «انجام‌شده» تغییر می‌کند، تلاش می‌کند پاداشِ معرف را
 * پرداخت کند. خودِ سرویس شرطِ «پرداخت‌شده بودنِ فاکتور» و گاردِ یک‌بار پرداخت را
 * چک می‌کند، پس این مسیر و مسیرِ «پرداختِ فاکتور» مکملِ هم و idempotent‌اند.
 */
class OrderReferralObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $isCompleted = $order->status instanceof OrderStatus
            ? $order->status === OrderStatus::Completed
            : (string) $order->status === OrderStatus::Completed->value;

        if (! $isCompleted) {
            return;
        }

        try {
            app(ReferralService::class)->rewardReferrerIfEligible($order);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('crm.referral.reward_failed_on_complete', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
