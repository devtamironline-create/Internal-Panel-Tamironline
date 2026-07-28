<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Log;
use Modules\CRM\Enums\SmsTrigger;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\SmsLog;
use Modules\CRM\Models\SmsTemplate;
use Modules\SMS\Services\KavenegarService;

/**
 * سرویس مسئول ارسال خودکار SMS بر اساس رویدادهای سفارش.
 *
 * هیچ‌وقت خطای ارسال، جریان اصلی (ثبت/تغییر سفارش) را متوقف نمی‌کند
 * — شکست SMS فقط لاگ می‌شود.
 */
class OrderSmsNotifier
{
    public function __construct(protected KavenegarService $sms) {}

    public function notify(Order $order, SmsTrigger $trigger, ?int $sentBy = null, array $extraVars = []): void
    {
        try {
            $template = SmsTemplate::forTrigger($trigger->value);
            if (! $template) {
                return; // قالب غیرفعال یا وجود ندارد → سایلنت
            }

            [$recipient, $role] = $this->resolveRecipient($order, $template->recipient);
            if (! $recipient) {
                return;
            }

            $order->loadMissing(['technician', 'customer']);
            $vars = array_merge($this->buildVariables($order), $extraVars);

            // ─── دو حالت ارسال ─────────────────────────────────
            // ۱) اگر kavenegar_template ست است → ارسال template-based
            //    (verify/lookup.json) با token/token2/token3
            // ۲) در غیر این‌صورت → ارسال متن آزاد (sms/send.json)
            if (! empty($template->kavenegar_template)) {
                $tokens = $template->renderTokens($vars);
                $result = $this->sms->sendTemplate($recipient, $template->kavenegar_template, $tokens);

                SmsLog::create([
                    'order_id' => $order->id,
                    'trigger_key' => $trigger->value,
                    'recipient_mobile' => $recipient,
                    'recipient_role' => $role,
                    'body' => $template->kavenegar_template.' | '.json_encode($tokens, JSON_UNESCAPED_UNICODE),
                    'status' => $result['success'] ? 'success' : 'failed',
                    'response' => $result['success'] ? null : ($result['message'] ?? null),
                    'sent_by' => $sentBy,
                    'created_at' => now(),
                ]);

                return;
            }

            // Fallback: متن آزاد
            $body = $template->render($vars);
            $this->dispatch($order, $trigger->value, $recipient, $role, $body, $sentBy);
        } catch (\Throwable $e) {
            Log::error('CRM OrderSmsNotifier failed', [
                'order_id' => $order->id,
                'trigger' => $trigger->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * ارسالِ پیامک‌های یک تغییرِ وضعیت — پیامکِ اصلی به‌علاوهٔ پیامک‌های
     * همراه.
     *
     * چرا لازم است: هر trigger یک قالب دارد و هر قالب یک گیرنده. برای
     * لغو سفارش باید هم مشتری خبردار شود هم تکنسین، پس دو trigger جدا
     * صدا زده می‌شود. قالب‌های غیرفعال خودشان سایلنت رد می‌شوند، پس
     * فراخوانی بی‌خطر است.
     *
     * @return array<int, string> کلیدِ triggerهایی که تلاش شد
     */
    public function notifyStatusChange(Order $order, \Modules\CRM\Enums\OrderStatus $status, ?int $sentBy = null): array
    {
        $sent = [];

        if ($primary = SmsTrigger::fromOrderStatus($status)) {
            $this->notify($order, $primary, $sentBy);
            $sent[] = $primary->value;
        }

        foreach (SmsTrigger::companionsForOrderStatus($status) as $companion) {
            $this->notify($order, $companion, $sentBy);
            $sent[] = $companion->value;
        }

        return $sent;
    }

    /**
     * ارسال دستی پیامک آزاد به مشتری یا تکنسین سفارش.
     */
    public function sendManual(Order $order, string $toRole, string $body, ?int $sentBy = null): array
    {
        [$recipient, $role] = $this->resolveRecipient($order, $toRole);
        if (! $recipient) {
            return ['success' => false, 'message' => 'گیرنده در دسترس نیست.'];
        }

        $result = $this->dispatch($order, null, $recipient, $role, $body, $sentBy);

        return $result;
    }

    /**
     * @return array{0: ?string, 1: ?string} [mobile, role]
     */
    protected function resolveRecipient(Order $order, string $recipientRole): array
    {
        if ($recipientRole === 'technician') {
            $mobile = $order->technician?->mobile;

            return $mobile ? [$mobile, 'technician'] : [null, null];
        }

        return [$order->customer_mobile ?: $order->customer?->mobile, 'customer'];
    }

    protected function buildVariables(Order $order): array
    {
        $techFullName = $order->technician
            ? trim((string) ($order->technician->firstname_tech
                ?: ($order->technician->first_name ?? '').' '.($order->technician->last_name ?? '')))
            : '';

        return [
            'customer_name' => $order->customer_name ?: $order->customer?->display_name ?: '',
            'customer_mobile' => $order->customer_mobile ?: '',
            'order_code' => $order->order_code ?: '',
            'order_id' => (string) $order->id,
            'technician_name' => $techFullName,
            'technician_mobile' => $order->technician?->mobile ?? '',
            'status' => $order->status?->label() ?? '',
            'shop_name' => CrmSetting::get('shop_name', ''),
            'visit_date' => $order->visit_scheduled_at?->format('Y-m-d H:i') ?? '',
            'subscription' => (string) ($order->customer?->subscription ?? ''),
            'order_type' => 'تعمیر',
            'amount' => (string) ($order->final_price ?: $order->total_invoice ?: $order->price_customer ?: 0),
            'cancel_reason' => trim((string) ($order->cancel_reason ?? '')),
            'pay_link' => '',
        ];
    }

    protected function dispatch(
        Order $order,
        ?string $triggerKey,
        string $recipient,
        ?string $role,
        string $body,
        ?int $sentBy
    ): array {
        $result = $this->sms->send($recipient, $body);

        SmsLog::create([
            'order_id' => $order->id,
            'trigger_key' => $triggerKey,
            'recipient_mobile' => $recipient,
            'recipient_role' => $role,
            'body' => $body,
            'status' => $result['success'] ? 'success' : 'failed',
            'response' => $result['success'] ? null : ($result['message'] ?? null),
            'sent_by' => $sentBy,
            'created_at' => now(),
        ]);

        return $result;
    }
}
