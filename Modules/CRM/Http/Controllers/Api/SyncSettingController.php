<?php

namespace Modules\CRM\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\SmsTemplate;
use Throwable;

/**
 * Endpointهای سینک تنظیمات از CRM وردپرسی.
 *
 * هر گزینه‌ای که از WP می‌آید با پیشوند `wp.` در crm_settings ذخیره می‌شود
 * تا با تنظیمات بومی لاراول (مثل zibal_merchant) قاطی نشود. مقادیر آرایه/شیء
 * به‌صورت JSON ذخیره می‌شوند تا بعداً با CrmSetting::getJson خوانده شوند.
 *
 * استثناء: هنگام دریافت key=sms، علاوه بر ذخیرهٔ تنظیمات، بدنهٔ قالب‌های
 * متناظر در crm_sms_templates نیز به‌روزرسانی می‌شود تا OrderSmsNotifier
 * بدون نیاز به تغییر دستی، از متن وارد شده در WP استفاده کند.
 */
class SyncSettingController extends Controller
{
    /** پیشوندی که برای جلوگیری از تداخل با تنظیمات لاراول اضافه می‌شود. */
    public const PREFIX = 'wp.';

    /**
     * نگاشت کلید WP در آرایهٔ sms به trigger_key لاراول.
     * فقط کلیدهایی که معادل واضح در crm_sms_templates دارند فهرست شده‌اند.
     */
    private const SMS_KEY_MAP = [
        'customer_order'        => 'order_created',
        'technician_order'      => 'order_assigned_tech',
        'customer_cancel_order' => 'order_cancelled',
    ];

    /** سینک تکی — POST /api/crm/sync/setting */
    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => 'required|string|max:191',
            'value' => 'present',
        ]);

        $stored = $this->storeOne($data['key'], $data['value']);

        return response()->json([
            'ok' => true,
            'key' => $stored,
        ]);
    }

    /** سینک دسته‌ای — POST /api/crm/sync/settings/batch */
    public function batch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array|min:1|max:200',
            'items.*.key' => 'required|string|max:191',
            'items.*.value' => 'present',
        ]);

        $saved = 0;
        $errors = [];

        foreach ($data['items'] as $i => $item) {
            try {
                $this->storeOne($item['key'], $item['value']);
                $saved++;
            } catch (Throwable $e) {
                $errors[] = [
                    'index' => $i,
                    'key' => $item['key'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'total' => count($data['items']),
            'saved' => $saved,
            'errors' => $errors,
        ]);
    }

    /**
     * یک گزینه را با کلید پیشوندار `wp.<key>` ذخیره می‌کند.
     * Scalar → رشتهٔ خام. Array/object → JSON.
     */
    protected function storeOne(string $key, mixed $value): string
    {
        $finalKey = self::PREFIX . ltrim($key, '.');

        if (is_array($value) || is_object($value)) {
            CrmSetting::setJson($finalKey, $value);
        } else {
            CrmSetting::set($finalKey, $value === null ? null : (string) $value);
        }

        // اثر جانبی: اگر گزینهٔ sms آمد، body قالب‌های متناظر را در
        // crm_sms_templates به‌روز کن (بدون تغییر فعال/غیرفعال بودنشان).
        if ($key === 'sms' && is_array($value)) {
            $this->applySmsTemplates($value);
        }

        return $finalKey;
    }

    /**
     * بدنهٔ قالب‌های sms را روی crm_sms_templates سینک می‌کند.
     * فقط ستون body به‌روز می‌شود تا is_active دست‌نخورده بماند.
     */
    protected function applySmsTemplates(array $sms): void
    {
        foreach (self::SMS_KEY_MAP as $wpKey => $triggerKey) {
            if (! array_key_exists($wpKey, $sms)) {
                continue;
            }
            $body = trim((string) $sms[$wpKey]);
            if ($body === '') {
                continue;
            }
            SmsTemplate::where('trigger_key', $triggerKey)->update(['body' => $body]);
        }
    }
}

