<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\CustomerWalletTxType;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Order;

/**
 * پاداشِ معرف: وقتی کاربرِ معرفی‌شده «اولین سفارشِ تکمیل‌شده و پرداخت‌شده» را
 * داشت، به معرفِ او یک‌بار مبلغِ ثابت (پیش‌فرض ۲۰۰٬۰۰۰ تومان) شارژ می‌شود.
 *
 * idempotent: با مهرِ referral_rewarded_at روی مشتریِ معرفی‌شده و قفلِ ردیف،
 * پاداش هرگز دوباره پرداخت نمی‌شود.
 */
class ReferralService
{
    public const REWARD_SETTING_KEY = 'customer_referral_reward';

    public const DEFAULT_REWARD = 200000; // تومان

    public function __construct(private readonly CustomerWalletService $wallet) {}

    /** مبلغِ پاداشِ قابلِ‌تنظیم (تومان). */
    public function rewardAmount(): int
    {
        $v = (int) (CrmSetting::get(self::REWARD_SETTING_KEY) ?? self::DEFAULT_REWARD);

        return $v > 0 ? $v : self::DEFAULT_REWARD;
    }

    /**
     * اگر سفارش شرایط را داشته باشد، به معرفِ مشتری پاداش می‌دهد. از هر دو مسیرِ
     * «تکمیلِ سفارش» و «پرداختِ فاکتور» با خیال راحت صدا زده می‌شود (idempotent).
     */
    public function rewardReferrerIfEligible(Order $order): void
    {
        // شرط‌های سبک (بدونِ قفل) — قبل از ورود به transaction.
        if (! $this->orderCompletedAndPaid($order)) {
            return;
        }
        $customerId = $order->customer_id;
        if (! $customerId) {
            return;
        }

        DB::transaction(function () use ($customerId, $order) {
            /** @var Customer|null $referred */
            $referred = Customer::whereKey($customerId)->lockForUpdate()->first();
            if (! $referred || ! $referred->referred_by || $referred->referral_rewarded_at !== null) {
                return; // معرف ندارد یا قبلاً پاداش گرفته.
            }

            $referrer = Customer::query()->active()->whereKey($referred->referred_by)->first();
            if (! $referrer) {
                return;
            }

            $this->wallet->credit($referrer, CustomerWalletTxType::ReferralReward, $this->rewardAmount(), [
                'order' => $order,
                'note' => 'پاداش معرفی مشتری '.($referred->mobile ?? ('#'.$referred->id)),
                'meta' => ['referred_customer_id' => $referred->id, 'order_id' => $order->id],
            ]);

            $referred->forceFill(['referral_rewarded_at' => now()])->save();
        });
    }

    /** آیا سفارش هم تکمیل شده و هم فاکتورش پرداخت شده است؟ */
    private function orderCompletedAndPaid(Order $order): bool
    {
        $isCompleted = $order->status instanceof \Modules\CRM\Enums\OrderStatus
            ? $order->status === \Modules\CRM\Enums\OrderStatus::Completed
            : (string) $order->status === \Modules\CRM\Enums\OrderStatus::Completed->value;

        if (! $isCompleted) {
            return false;
        }

        // حداقل یک فاکتورِ پرداخت‌شده برای این سفارش.
        return $order->invoices()->where('status', 'paid')->exists();
    }
}
