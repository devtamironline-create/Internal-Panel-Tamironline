<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Log;
use Modules\CRM\Enums\SmsTrigger;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\SmsLog;
use Modules\CRM\Models\SmsTemplate;
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

        // تمپلیتِ «رسید انتقال» از صفحهٔ «مدیریت پیامک». اگر ثبت و فعال باشد و
        // نامِ تمپلیتِ کاوه‌نگار داشته باشد، از verify/lookup استفاده می‌شود؛
        // در غیرِ این‌صورت به متنِ ساده (رفتارِ قبلی) برمی‌گردیم تا در
        // پروداکشن چیزی خراب نشود.
        $template = null;
        try {
            $template = SmsTemplate::where('trigger_key', SmsTrigger::CustomerTransferReceipt->value)->first();
        } catch (\Throwable $e) {
            // نبودِ جدول/تمپلیت نباید ثبتِ رسید را بشکند.
        }

        $useTemplate = $template && $template->is_active && ! empty($template->kavenegar_template);

        $body = ($name !== '' ? $name.' عزیز، ' : '')
            .'رسید انتقال دستگاه شما ثبت شد. مشاهده:'."\n".$link."\n".'تعمیرآنلاین';
        $logBody = $body;

        try {
            if ($useTemplate) {
                $vars = [
                    'customer_name' => $name,
                    'order_code' => (string) ($order->order_code ?? ''),
                    'receipt_code' => (string) $receipt->code,
                    'receipt_url' => $link,
                ];
                $tokens = $template->renderTokens($vars);
                $logBody = $template->kavenegar_template.' | '.json_encode($tokens, JSON_UNESCAPED_UNICODE);
                $result = $this->sms->sendTemplate($mobile, $template->kavenegar_template, $tokens);
            } else {
                $result = $this->sms->send($mobile, $body);
            }
            $ok = ! empty($result['success']);
        } catch (\Throwable $e) {
            $result = ['message' => $e->getMessage()];
            $ok = false;
            Log::warning('transfer_receipt.sms_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        try {
            SmsLog::create([
                'order_id' => $order->id,
                'trigger_key' => SmsTrigger::CustomerTransferReceipt->value,
                'recipient_mobile' => $mobile,
                'recipient_role' => 'customer',
                'body' => $logBody,
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
