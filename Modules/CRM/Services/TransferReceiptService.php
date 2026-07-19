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
     * فقط رسید را می‌سازد (بدونِ ارسالِ پیامک). ارسالِ پیامک جداگانه و «دستی»
     * توسطِ تکنسین انجام می‌شود (sendSms) تا فقط یک‌بار و با ارادهٔ او فرستاده شود.
     */
    public function create(Order $order, ?string $description, ?int $userId = null, ?int $techId = null): TransferReceipt
    {
        return TransferReceipt::create([
            'order_id' => $order->id,
            'description' => $description,
            'created_by' => $userId,
            'created_by_tech_id' => $techId,
        ]);
    }

    /**
     * رسید را می‌سازد و بلافاصله پیامک را می‌فرستد (برای ثبتِ ادمین که خودش
     * می‌خواهد همان لحظه اطلاع‌رسانی شود). با force، محدودیتِ «یک‌بار» را دور می‌زند.
     */
    public function createAndNotify(Order $order, ?string $description, ?int $userId = null, ?int $techId = null): TransferReceipt
    {
        $receipt = $this->create($order, $description, $userId, $techId);
        $this->sendSms($order, $receipt, $userId, true);

        return $receipt;
    }

    /**
     * ارسالِ پیامکِ رسید به مشتری. به‌صورتِ پیش‌فرض «یک‌بار»؛ اگر قبلاً ارسال شده
     * باشد و force نباشد، دوباره ارسال نمی‌شود و false برمی‌گرداند (قانونِ تکنسین:
     * فقط یک‌بار). ادمین می‌تواند با force=true ارسالِ مجدد کند.
     *
     * @return bool موفقیتِ ارسال (یا false اگر قبلاً ارسال شده و force نبود)
     */
    public function sendSms(Order $order, TransferReceipt $receipt, ?int $userId = null, bool $force = false): bool
    {
        if (! $force && $receipt->sms_sent_at !== null) {
            return false;
        }

        $this->notify($order, $receipt, $userId);

        $receipt->forceFill([
            'sms_sent_at' => now(),
            'sms_sent_by' => $userId,
        ])->save();

        return true;
    }

    /**
     * لینکِ عمومیِ رسید (نمای HTMLِ پنل) — مبنایِ دکمهٔ «دانلود PDF» در اپ و
     * فیلدِ print_url در API. قابلِ تنظیم با transfer_receipt_url_template ({token}).
     */
    public static function publicUrl(TransferReceipt $receipt): string
    {
        $template = trim((string) CrmSetting::get('transfer_receipt_url_template', ''));
        if ($template !== '' && str_contains($template, '{token}')) {
            return str_replace('{token}', $receipt->token, $template);
        }

        return route('crm.transfer-receipt.public', $receipt->token);
    }

    /**
     * لینکی که با پیامک به مشتری می‌رود — به صفحهٔ همان سفارش در اپ می‌رود تا
     * مشتری رسید را داخلِ سفارش ببیند و در صورتِ نیاز PDF بگیرد. قابلِ تنظیم با
     * transfer_receipt_app_url_template و placeholderهای {order_id}/{order_code}/{token}.
     * اگر تنظیم خالی شود به نمای پنل fallback می‌کند (ایمن).
     */
    public static function appLink(Order $order, TransferReceipt $receipt): string
    {
        $template = trim((string) CrmSetting::get(
            'transfer_receipt_app_url_template',
            'https://app.tamironline.com/orders/{order_id}'
        ));
        if ($template === '') {
            return self::publicUrl($receipt);
        }

        return strtr($template, [
            '{order_id}' => (string) $order->id,
            '{order_code}' => (string) ($order->order_code ?? ''),
            '{token}' => (string) $receipt->token,
        ]);
    }

    /** ارسالِ SMS با لینک — best-effort. */
    protected function notify(Order $order, TransferReceipt $receipt, ?int $userId): void
    {
        $mobile = $order->customer_mobile ?: optional($order->customer)->mobile;
        if (! $mobile) {
            return;
        }

        $name = trim((string) ($order->customer_name ?: optional($order->customer)->display_name ?? ''));
        // لینکِ پیامک → صفحهٔ سفارش در اپ (نه نمای پنل)؛ رسید داخلِ سفارش دیده می‌شود.
        $link = self::appLink($order, $receipt);

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
