<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Log;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\SmsLog;
use Modules\CRM\Models\TransferReceipt;
use Modules\SMS\Services\KavenegarService;

/**
 * ساختِ رسیدِ انتقال + ارسالِ لینک با SMS به مشتری. ارسالِ SMS best-effort است
 * و هرگز نباید ثبتِ رسید را بشکند.
 */
class TransferReceiptService
{
    public function __construct(protected KavenegarService $sms) {}

    /** آیا سیستمِ رسیدِ انتقال فعال است؟ (پیش‌فرض: فعال، مگر صریحاً '0') */
    public static function enabled(): bool
    {
        return CrmSetting::get('transfer_receipt_enabled', '1') === '1';
    }

    /**
     * رسید را می‌سازد و لینکش را با SMS برای مشتری می‌فرستد.
     */
    public function createAndNotify(Order $order, ?string $description, ?int $userId = null, ?int $techId = null): TransferReceipt
    {
        $receipt = TransferReceipt::create([
            'order_id' => $order->id,
            'description' => $description,
            'created_by' => $userId,
            'created_by_tech_id' => $techId,
        ]);

        $this->notify($order, $receipt, $userId);

        return $receipt;
    }

    /** لینکِ عمومیِ رسید — قابلِ تنظیم با transfer_receipt_url_template ({token}). */
    public static function publicUrl(TransferReceipt $receipt): string
    {
        $template = trim((string) CrmSetting::get('transfer_receipt_url_template', ''));
        if ($template !== '' && str_contains($template, '{token}')) {
            return str_replace('{token}', $receipt->token, $template);
        }

        return route('crm.transfer-receipt.public', $receipt->token);
    }

    /** ارسالِ SMS با لینک — best-effort. */
    protected function notify(Order $order, TransferReceipt $receipt, ?int $userId): void
    {
        $mobile = $order->customer_mobile ?: optional($order->customer)->mobile;
        if (! $mobile) {
            return;
        }

        $name = trim((string) ($order->customer_name ?: optional($order->customer)->display_name ?? ''));
        $link = self::publicUrl($receipt);
        $body = ($name !== '' ? $name.' عزیز، ' : '')
            .'رسید انتقال دستگاه شما ثبت شد. مشاهده:'."\n".$link."\n".'تعمیرآنلاین';

        try {
            $result = $this->sms->send($mobile, $body);
            $ok = ! empty($result['success']);
        } catch (\Throwable $e) {
            $result = ['message' => $e->getMessage()];
            $ok = false;
            Log::warning('transfer_receipt.sms_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        try {
            SmsLog::create([
                'order_id' => $order->id,
                'trigger_key' => 'customer_transfer_receipt',
                'recipient_mobile' => $mobile,
                'recipient_role' => 'customer',
                'body' => $body,
                'status' => $ok ? 'success' : 'failed',
                'response' => $ok ? null : ($result['message'] ?? null),
                'sent_by' => $userId,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // لاگِ SMS نباید جریان را بشکند.
        }
    }
}
