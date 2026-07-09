<?php

namespace Modules\CRM\Services;

use Modules\CRM\Enums\SmsTrigger;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Proforma;
use Modules\CRM\Models\SmsLog;
use Modules\CRM\Models\SmsTemplate;
use Modules\SMS\Services\KavenegarService;

/**
 * ساخت و ارسالِ پیش‌فاکتور — مسیرِ مشترکِ ادمین و تکنسین.
 *
 * منطقِ ساخت (نرمال‌سازیِ اقلام، تخفیف، snapshotِ مشتری/دستگاه) یک‌جا نگه
 * داشته می‌شود تا پنل و اپ رفتارِ یکسان داشته باشند.
 */
class ProformaService
{
    /**
     * ساختِ پیش‌فاکتور.
     *
     * @param  array<string, mixed>  $data
     *                                      items: array<int,{title,quantity,unit_price}>, discount, description,
     *                                      valid_until, customer_name, customer_mobile, device_name, brand_name
     */
    public function create(array $data, ?Order $order = null, ?int $userId = null, ?int $techId = null): Proforma
    {
        $normalized = Proforma::normalizeItems((array) ($data['items'] ?? []));
        $subtotal = $normalized['subtotal'];
        $discount = max(0, min($subtotal, (int) ($data['discount'] ?? 0)));
        $total = max(0, $subtotal - $discount);

        // snapshotِ مشتری/دستگاه: از سفارش (اگر هست) وگرنه از ورودی.
        $customerName = $data['customer_name'] ?? ($order?->customer_name ?: $order?->customer?->display_name);
        $customerMobile = $data['customer_mobile'] ?? ($order?->customer_mobile ?: $order?->customer?->mobile);
        $deviceName = $data['device_name'] ?? $order?->device?->name;
        $brandName = $data['brand_name'] ?? $order?->brand?->name;

        return Proforma::create([
            'order_id' => $order?->id,
            'customer_id' => $order?->customer_id,
            'technician_id' => $order?->technician_id ?? $techId,
            'customer_name' => $customerName ? mb_substr((string) $customerName, 0, 150) : null,
            'customer_mobile' => $customerMobile ? mb_substr((string) $customerMobile, 0, 20) : null,
            'device_name' => $deviceName ? mb_substr((string) $deviceName, 0, 120) : null,
            'brand_name' => $brandName ? mb_substr((string) $brandName, 0, 120) : null,
            'items' => $normalized['items'],
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'description' => isset($data['description']) ? mb_substr(trim((string) $data['description']), 0, 2000) : null,
            'valid_until' => $data['valid_until'] ?? null,
            'status' => 'draft',
            'created_by_user_id' => $userId,
            'created_by_tech_id' => $techId,
        ]);
    }

    /**
     * ساخت + ارسالِ خودکارِ پیامک برای مشتری (رفتارِ پیش‌فرضِ پنل و اپ).
     * پیامک بلافاصله پس از ساخت ارسال می‌شود؛ اگر ناموفق بود، پیش‌فاکتور
     * ساخته‌شده باقی می‌ماند (قابلِ ارسالِ مجدد از دکمهٔ «ارسال پیامک»).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: Proforma, 1: bool, 2: string} [proforma, smsOk, smsMessage]
     */
    public function createAndSend(array $data, ?Order $order = null, ?int $userId = null, ?int $techId = null): array
    {
        $proforma = $this->create($data, $order, $userId, $techId);
        [$ok, $message] = $this->sendSms($proforma);

        return [$proforma, $ok, $message];
    }

    /**
     * ارسالِ لینکِ عمومیِ پیش‌فاکتور به موبایلِ مشتری (تمپلیتِ
     * customer_proforma_issued). خروجی: [ok(bool), message(string)].
     *
     * @return array{0: bool, 1: string}
     */
    public function sendSms(Proforma $proforma, ?KavenegarService $sms = null): array
    {
        $sms ??= app(KavenegarService::class);

        $mobile = $proforma->customer_mobile;
        if (! $mobile) {
            return [false, 'شماره موبایل مشتری ثبت نشده است.'];
        }

        $trigger = SmsTrigger::CustomerProformaIssued;
        $template = SmsTemplate::where('trigger_key', $trigger->value)->first();
        if (! $template) {
            return [false, 'تمپلیت "customer_proforma_issued" در پنل ثبت نشده است.'];
        }
        if (! $template->is_active) {
            return [false, 'تمپلیت "customer_proforma_issued" غیرفعال است — از تنظیمات SMS فعالش کنید.'];
        }
        if (empty($template->kavenegar_template)) {
            return [false, 'فیلد kavenegar_template برای این تمپلیت خالی است — یک نام تمپلیت تأییدشده کاوه‌نگار وارد کنید.'];
        }

        $vars = [
            'customer_name' => (string) ($proforma->customer_name ?? ''),
            'proforma_code' => (string) $proforma->proforma_code,
            'amount' => (string) (int) $proforma->total,
            'receipt_url' => $proforma->publicUrl(),
            'public_token' => (string) $proforma->public_token,
        ];
        $tokens = $template->renderTokens($vars);

        $result = $sms->sendTemplate($mobile, $template->kavenegar_template, $tokens);

        SmsLog::create([
            'order_id' => $proforma->order_id,
            'trigger_key' => $trigger->value,
            'recipient_mobile' => $mobile,
            'recipient_role' => 'customer',
            'body' => $template->kavenegar_template.' | '.json_encode($tokens, JSON_UNESCAPED_UNICODE),
            'status' => ! empty($result['success']) ? 'success' : 'failed',
            'response' => ! empty($result['success']) ? null : ($result['message'] ?? null),
            'sent_by' => auth()->id(),
            'created_at' => now(),
        ]);

        if (! empty($result['success'])) {
            if ($proforma->status === 'draft') {
                $proforma->update(['status' => 'sent', 'sent_at' => now()]);
            }

            return [true, 'پیش‌فاکتور به '.$mobile.' ارسال شد.'];
        }

        return [false, 'ارسال پیامک ناموفق: '.($result['message'] ?? 'خطای ناشناخته از کاوه‌نگار')];
    }

    /**
     * شکلِ استانداردِ خروجی برای APIِ اپ (مشتری/تکنسین) و رسیدِ عمومی.
     *
     * @return array<string, mixed>
     */
    public function shape(Proforma $proforma): array
    {
        return [
            'id' => (int) $proforma->id,
            'code' => $proforma->proforma_code,
            'token' => $proforma->public_token,
            'status' => $proforma->status,
            'status_label' => $proforma->statusLabel(),
            'customer_name' => $proforma->customer_name,
            'customer_mobile' => $proforma->customer_mobile,
            'device_name' => $proforma->device_name,
            'brand_name' => $proforma->brand_name,
            'items' => array_map(fn ($i) => [
                'title' => $i['title'] ?? '',
                'quantity' => (int) ($i['quantity'] ?? 1),
                'unit_price' => (int) ($i['unit_price'] ?? 0),
                'total' => (int) ($i['total'] ?? 0),
            ], (array) $proforma->items),
            'subtotal' => (int) $proforma->subtotal,
            'discount' => (int) $proforma->discount,
            'total' => (int) $proforma->total,
            'description' => $proforma->description,
            'valid_until' => $proforma->valid_until?->toDateString(),
            // اعتبارِ ۲۴ ساعته از لحظهٔ ساخت — پس از آن خودکار «منقضی» می‌شود.
            'expires_at' => $proforma->expiresAt()?->utc()->toIso8601String(),
            'is_expired' => $proforma->isExpired(),
            'order_id' => $proforma->order_id,
            'order_code' => $proforma->order?->order_code,
            'public_url' => $proforma->publicUrl(),
            'created_at' => $proforma->created_at?->utc()->toIso8601String(),
        ];
    }
}
