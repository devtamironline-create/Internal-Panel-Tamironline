<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;

/**
 * Push تغییرات سفارش از Laravel به WP CRM (سینک معکوس).
 *
 * تنظیمات لازم در crm_settings:
 *   wp_push_url      → آدرس پایه‌ی سایت WP، بدون اسلش (مثل https://crm.tamironline.com)
 *   wp_push_secret   → کلید HMAC که در پلاگین به‌عنوان tcs_inbound_secret هم باید ست شود
 *   wp_push_enabled  → '1' برای فعال‌سازی
 *
 * مَپ فیلدهای Laravel → WP در Inbound endpoint پلاگین انجام می‌شود؛
 * اینجا فقط نام‌های Laravel را استفاده می‌کنیم.
 */
class WpPushService
{
    /**
     * فیلدهای Laravel که می‌توانیم به WP پوش کنیم. هر کلید دیگری در
     * call ها silently ignore می‌شود تا اطلاعات حساس به اشتباه نرود.
     */
    private const PUSHABLE_FIELDS = [
        'status', 'technician', 'visit_date', 'visit_time',
        'description_tech', 'description_tech1', 'description_tech2',
        'piece_list', 'buy_price_list', 'customer_price_list',
        'price_customer', 'cost_price', 'total_invoice',
        'hire', 'transportation', 'discount',
        'invoice_descripotion', 'cancel_reason',
        'return_type', 'return_description',
        'save_as_draft',
    ];

    public function isEnabled(): bool
    {
        return CrmSetting::get('wp_push_enabled') === '1'
            && filled(CrmSetting::get('wp_push_url'))
            && filled(CrmSetting::get('wp_push_secret'));
    }

    /**
     * Push کل وضعیت یک سفارش به WP. فقط فیلدهایی که مقدار دارند ارسال
     * می‌شوند تا metaهای WP غیرضروری null نشوند.
     */
    public function pushOrder(Order $order): void
    {
        if (! $this->isEnabled() || ! $order->wp_id) {
            return;
        }

        $fields = $this->extractFields($order);
        if (empty($fields)) {
            return;
        }

        $this->send([
            'wp_id' => (int) $order->wp_id,
            'fields' => $fields,
        ]);
    }

    /**
     * استخراج فیلدهای قابل پوش از یک Order. وضعیت به کد عددی WP،
     * زمان مراجعه به scheduled_date+scheduled_time جدا، و technician به
     * wp_id کاربر تبدیل می‌شود.
     */
    protected function extractFields(Order $order): array
    {
        $fields = [];

        // وضعیت → کد عددی WP
        $status = $order->status instanceof OrderStatus ? $order->status : OrderStatus::tryFrom((string) $order->status);
        if ($status) {
            $fields['status'] = (string) $status->wpCode();
        }

        // تکنسین → wp_id کاربر WP
        if ($order->technician_id) {
            $tech = Technician::find($order->technician_id);
            if ($tech && $tech->wp_id) {
                $fields['technician'] = (int) $tech->wp_id;
            }
        } elseif ($order->wasChanged('technician_id')) {
            // unassign — حذف
            $fields['technician'] = null;
        }

        // زمان مراجعه → جلالی date + ساعت
        if ($order->visit_scheduled_at) {
            $jalali = \Morilog\Jalali\Jalalian::fromCarbon($order->visit_scheduled_at);
            $fields['visit_date'] = $jalali->format('Y/m/d');
            $fields['visit_time'] = $order->visit_scheduled_at->format('H:i');
        } elseif ($order->wasChanged('visit_scheduled_at')) {
            $fields['visit_date'] = null;
            $fields['visit_time'] = null;
        }

        // متن‌های تکنسین
        foreach (['description_tech', 'description_tech1', 'description_tech2'] as $f) {
            if ($order->{$f} !== null) {
                $fields[$f] = (string) $order->{$f};
            }
        }

        // قطعات و مالی
        foreach (['piece_list', 'buy_price_list', 'customer_price_list'] as $f) {
            if (is_array($order->{$f}) && ! empty($order->{$f})) {
                $fields[$f] = $order->{$f};
            }
        }
        foreach (['price_customer', 'cost_price', 'total_invoice', 'hire', 'transportation', 'discount'] as $f) {
            if ($order->{$f} !== null) {
                $fields[$f] = (int) $order->{$f};
            }
        }

        if ($order->invoice_descripotion !== null) {
            $fields['invoice_descripotion'] = (string) $order->invoice_descripotion;
        }
        if ($order->cancel_reason !== null) {
            $fields['cancel_reason'] = (string) $order->cancel_reason;
        }
        if ($order->return_type !== null) {
            $fields['return_type'] = (string) $order->return_type;
        }
        if ($order->return_description !== null) {
            $fields['return_description'] = (string) $order->return_description;
        }
        if ($order->save_as_draft !== null) {
            $fields['save_as_draft'] = (bool) $order->save_as_draft ? '1' : '0';
        }

        // فقط فیلدهای مجاز
        return array_intersect_key($fields, array_flip(self::PUSHABLE_FIELDS));
    }

    /** ارسال HTTP با امضای HMAC. خرابی نباید درخواست اصلی را شکست‌بدهد. */
    protected function send(array $payload): void
    {
        $url = rtrim((string) CrmSetting::get('wp_push_url'), '/') . '/wp-json/tcs/v1/order-update';
        $secret = (string) CrmSetting::get('wp_push_secret');
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $body, $secret);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-TCS-Signature' => $signature,
            ])->timeout(8)->withBody($body, 'application/json')->post($url);

            if (! $response->ok()) {
                Log::warning('crm.wp_push.failed', [
                    'wp_id' => $payload['wp_id'] ?? null,
                    'status' => $response->status(),
                    'body' => substr((string) $response->body(), 0, 500),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('crm.wp_push.exception', [
                'wp_id' => $payload['wp_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
