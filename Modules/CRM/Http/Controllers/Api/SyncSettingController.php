<?php

namespace Modules\CRM\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\CrmSetting;
use Throwable;

/**
 * Endpointهای سینک تنظیمات از CRM وردپرسی.
 *
 * هر گزینه‌ای که از WP می‌آید با پیشوند `wp.` در crm_settings ذخیره می‌شود
 * تا با تنظیمات بومی لاراول (مثل zibal_merchant) قاطی نشود. مقادیر آرایه/شیء
 * به‌صورت JSON ذخیره می‌شوند تا بعداً با CrmSetting::getJson خوانده شوند.
 */
class SyncSettingController extends Controller
{
    /** پیشوندی که برای جلوگیری از تداخل با تنظیمات لاراول اضافه می‌شود. */
    public const PREFIX = 'wp.';

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

        return $finalKey;
    }
}
