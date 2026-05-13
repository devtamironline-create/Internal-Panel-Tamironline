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

        // جهت سینک تکنسین — اگر روی wp_to_laravel یا none باشد، outbound مسدود
        if ($order->technician_id) {
            $tech = Technician::find($order->technician_id);
            if ($tech && ! $tech->canPushOrder()) {
                $this->logSync('outbound', 'order-update',
                    ['wp_id' => (int) $order->wp_id],
                    null, 'skipped', null,
                    'blocked_by_technician_sync_direction:' . $tech->order_sync_direction,
                    ['entity_type' => 'order', 'entity_id' => $order->id]
                );
                return;
            }
        }

        $fields = $this->extractFields($order);
        if (empty($fields)) {
            return;
        }

        $this->send([
            'wp_id' => (int) $order->wp_id,
            'fields' => $fields,
        ], ['entity_type' => 'order', 'entity_id' => $order->id]);
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
    protected function send(array $payload, array $context = []): void
    {
        $this->sendTo('order-update', $payload, $context + ['entity_type' => 'order']);
    }

    /**
     * ارسال HTTP عمومی به یک endpoint inbound پلاگین.
     *
     * $context = ['entity_type' => 'order|technician|customer|wallet_tx|...',
     *             'entity_id' => int|null]  → برای ثبت در crm_sync_logs.
     */
    protected function sendTo(string $endpoint, array $payload, array $context = []): ?array
    {
        if (! $this->isEnabled()) {
            $this->logSync('outbound', $endpoint, $payload, null, 'skipped', null, 'wp_push_disabled_or_unconfigured', $context);
            return null;
        }
        $url = rtrim((string) CrmSetting::get('wp_push_url'), '/') . '/wp-json/tcs/v1/' . $endpoint;
        $secret = (string) CrmSetting::get('wp_push_secret');
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $body, $secret);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-TCS-Signature' => $signature,
            ])->timeout(8)->withBody($body, 'application/json')->post($url);

            $respJson = null;
            try { $respJson = $response->json(); } catch (\Throwable $e) { /* not JSON */ }

            if (! $response->ok()) {
                Log::warning('crm.wp_push.failed', [
                    'endpoint' => $endpoint,
                    'wp_id' => $payload['wp_id'] ?? null,
                    'status' => $response->status(),
                    'body' => substr((string) $response->body(), 0, 500),
                ]);
                $this->logSync('outbound', $endpoint, $payload, $respJson ?? ['raw' => substr((string) $response->body(), 0, 500)],
                    'failed', $response->status(), 'http_'.$response->status(), $context);
                return null;
            }
            $this->logSync('outbound', $endpoint, $payload, $respJson, 'success', $response->status(), null, $context);
            return $respJson;
        } catch (\Throwable $e) {
            Log::error('crm.wp_push.exception', [
                'endpoint' => $endpoint,
                'wp_id' => $payload['wp_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
            $this->logSync('outbound', $endpoint, $payload, null, 'failed', null, mb_substr($e->getMessage(), 0, 500), $context);
            return null;
        }
    }

    /** نوشتن یک ردیف لاگ سینک — هرگز exception پرتاب نمی‌کند. */
    protected function logSync(
        string $direction,
        string $endpoint,
        ?array $payload,
        ?array $response,
        string $status,
        ?int $httpStatus,
        ?string $error,
        array $context
    ): void {
        \Modules\CRM\Models\SyncLog::record([
            'direction' => $direction,
            'entity_type' => $context['entity_type'] ?? 'other',
            'entity_id' => $context['entity_id'] ?? null,
            'wp_id' => $payload['wp_id'] ?? ($context['wp_id'] ?? null),
            'endpoint' => $endpoint,
            'status' => $status,
            'http_status' => $httpStatus,
            'error_message' => $error,
            'payload' => $payload,
            'response' => $response,
        ]);
    }

    // ─────────── Push تکنسین ───────────
    public function pushTechnician(\Modules\CRM\Models\Technician $tech): void
    {
        if (! $this->isEnabled()) return;
        if (! $tech->mobile && ! $tech->wp_id) return;

        // WP CRM لیست تکنسین نام را از فیلد first_name می‌خواند، نه از
        // firstname_tech. اگر firstname_tech (نام کامل تجاری) موجود است،
        // همان را به‌عنوان first_name هم بفرست تا لیست WP کامل نمایش دهد.
        $fullName = trim((string) ($tech->firstname_tech ?? ''));
        if ($fullName === '') {
            $fullName = trim(($tech->first_name ?? '') . ' ' . ($tech->last_name ?? ''));
        }
        $firstNameForWp = $fullName !== '' ? $fullName : $tech->first_name;

        // نگاشت Laravel → WP. کلیدها همان فیلدهای user_meta هستند که WP CRM
        // می‌خواند. دو فیلد با تایپو/تفاوت حروف بزرگ-کوچک در WP وجود دارند
        // که اینجا به شکل صحیح WP فرستاده می‌شوند:
        //   ready_for_delivery (Laravel bool) → ready_for_derliver ('1'/'0')
        //   img_personal (Laravel)            → img_Personal (WP)
        $fields = array_filter([
            'first_name'        => $firstNameForWp,
            'firstname_tech'    => $tech->firstname_tech,
            'technician_id'     => $tech->technician_id,
            'national_code'     => $tech->national_code,
            'mobile'            => $tech->mobile,
            'phone'             => $tech->phone,
            'phone_force'       => $tech->phone_force,
            'address'           => $tech->address,
            'description'       => $tech->description,
            'percent'           => $tech->percent,
            'max_order'         => $tech->max_order,
            'max_price'         => $tech->max_price,
            'status'            => $tech->status,
            'type_tech'         => $this->mapTypeTechForWp($tech->type_tech),
            'province'          => $tech->province,
            'specialty'         => $tech->specialty,
            'type_of_calc_tech' => $tech->type_of_calc_tech,
            'tech_per_of_all'   => $tech->tech_per_of_all,
            'cart_img'          => $tech->cart_img,
            'img_Personal'      => $tech->img_personal,     // تفاوت حروف P/p
            'ready_for_derliver' => $tech->ready_for_delivery !== null
                ? ((bool) $tech->ready_for_delivery ? '1' : '0')
                : null,
            'role'              => 'technician',
        ], fn ($v) => $v !== null && $v !== '');

        $resp = $this->sendTo('technician-upsert', [
            'wp_id'  => $tech->wp_id ?: 0,
            'fields' => $fields,
        ], ['entity_type' => 'technician', 'entity_id' => $tech->id]);

        // اگر تکنسین جدید WP id برگشت داد، روی مدل ذخیره کن
        if ($resp && ! $tech->wp_id && ! empty($resp['wp_id'])) {
            $tech->forceFill(['wp_id' => (int) $resp['wp_id']])->saveQuietly();
        }
    }

    // ─────────── Push مشتری ───────────
    public function pushCustomer(\Modules\CRM\Models\Customer $customer): void
    {
        if (! $this->isEnabled()) return;
        if (! $customer->mobile && ! $customer->wp_id) return;

        $fields = array_filter([
            'first_name'   => $customer->first_name,
            'mobile'       => $customer->mobile,
            'phone'        => $customer->phone,
            'address'      => $customer->address,
            'subscription' => $customer->subscription,
            'introduction' => $customer->introduction,
            'postal_code'  => $customer->postal_code,
            'role'         => 'customer',
        ], fn ($v) => $v !== null && $v !== '');

        $resp = $this->sendTo('customer-upsert', [
            'wp_id'  => $customer->wp_id ?: 0,
            'fields' => $fields,
        ], ['entity_type' => 'customer', 'entity_id' => $customer->id]);

        if ($resp && ! $customer->wp_id && ! empty($resp['wp_id'])) {
            $customer->forceFill(['wp_id' => (int) $resp['wp_id']])->saveQuietly();
        }
    }

    // ─────────── Push تراکنش کیف‌پول ───────────
    public function pushWalletTransaction(\Modules\CRM\Models\WalletTransaction $tx): void
    {
        if (! $this->isEnabled()) return;
        $tech = $tx->technician_id ? Technician::find($tx->technician_id) : null;
        if (! $tech || ! $tech->wp_id) return; // بدون wp_id تکنسین، WP نمی‌تواند تخصیص دهد

        // جهت سینک کیف‌پول تکنسین — اگر روی wp_to_laravel یا none باشد، outbound مسدود
        if (! $tech->canPushWallet()) {
            $this->logSync('outbound', 'financial-upsert',
                ['wp_id' => $tx->wp_id ?: 0, 'technician_wp_id' => $tech->wp_id],
                null, 'skipped', null,
                'blocked_by_technician_sync_direction:' . $tech->wallet_sync_direction,
                ['entity_type' => 'wallet_tx', 'entity_id' => $tx->id]
            );
            return;
        }

        // مَپ نوع تراکنش به فیلدهای CRM وردپرسی
        $type = $tx->type instanceof \Modules\CRM\Enums\WalletTxType ? $tx->type->value : $tx->type;
        $amount = (int) $tx->amount;
        $isPositive = $amount > 0;
        $absAmount = abs($amount);

        $fields = array_filter([
            'technician_wp_id' => $tech->wp_id,
            'order_id'         => $tx->order_id,
            'description'      => $tx->note,
            'post_title'       => $this->walletTxTitle($type, $amount),
        ], fn ($v) => $v !== null && $v !== '');

        // پر کردن متاهای رایج CRM وردپرسی بسته به نوع
        if ($type === 'wallet_charge' || $type === 'adjustment') {
            $fields['wallet'] = '1';
            $fields['wallet_pay'] = $absAmount;
        }
        if ($type === 'reward') {
            $fields['reward_type'] = '1';
            $fields['rewardDesc'] = (string) ($tx->note ?? '');
        }
        if ($type === 'penalty') {
            $fields['reward_type'] = '2';
            $fields['rewardDesc'] = (string) ($tx->note ?? '');
        }

        $resp = $this->sendTo('financial-upsert', [
            'wp_id'  => $tx->wp_id ?: 0,
            'fields' => $fields,
        ], ['entity_type' => 'wallet_tx', 'entity_id' => $tx->id]);

        if ($resp && ! $tx->wp_id && ! empty($resp['wp_id'])) {
            $tx->forceFill(['wp_id' => (int) $resp['wp_id']])->saveQuietly();
        }
    }

    /**
     * نگاشت type_tech لاراولی به enum پذیرفته‌شدهٔ WP CRM.
     *
     * WP CRM فقط مقادیر external / internal / repair را در dropdown
     * تکنسین می‌فهمد. مقادیر لاراولی مثل install_repair باعث می‌شوند
     * تکنسین در WP به‌عنوان «خارجی» نمایش داده شود ولی نتواند سفارش
     * بگیرد. این تابع همه مقادیر ناشناخته را به 'external' تبدیل
     * می‌کند تا تکنسین فعال و قابل تخصیص در WP باشد.
     */
    protected function mapTypeTechForWp(?string $value): ?string
    {
        if ($value === null || $value === '') return null;

        $value = trim((string) $value);
        $allowed = ['external', 'internal', 'repair'];
        if (in_array($value, $allowed, true)) {
            return $value;
        }

        // پیش‌فرض: تکنسین خارجی (قابل تخصیص و سفارش‌پذیر)
        return 'external';
    }

    protected function walletTxTitle(string $type, int $amount): string
    {
        $abs = number_format(abs($amount));
        return match ($type) {
            'wallet_charge' => "شارژ کیف‌پول — {$abs}",
            'commission'    => "کمیسیون — {$abs}",
            'reward'        => "پاداش — {$abs}",
            'penalty'       => "جریمه — {$abs}",
            'adjustment'    => "تعدیل — {$abs}",
            default         => "تراکنش مالی — {$abs}",
        };
    }
}
