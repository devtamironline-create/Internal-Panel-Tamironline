<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Site\Models\SiteSetting;

/**
 * تنظیمات سراسری برای فرانت — اطلاعات تماس و لینک‌های مشترک که در
 * چند صفحه استفاده می‌شوند (header، hero، footer).
 *
 * منبع: جدول site_settings (key-value). فقط زیرمجموعه‌ی public کلیدها
 * بازگردانده می‌شوند تا کلیدهای حساس (مثل تنظیمات داخلی) لیک نکنند.
 */
class SettingsController extends Controller
{
    /**
     * GET /v1/settings/global
     */
    public function global(): JsonResponse
    {
        $values = SiteSetting::query()
            ->whereIn('group', ['general', 'contact', 'social'])
            ->pluck('value', 'key')
            ->all();

        // لیست شماره‌های برچسب‌دار — منبع اصلی contact_phones (JSON)، با fallback به contact_phone.
        $phones = $this->decodePhones($values['contact_phones'] ?? null);
        if (empty($phones)) {
            $legacy = $values['contact_phone'] ?? null;
            if ($legacy !== null && trim($legacy) !== '') {
                $phones[] = ['label' => '', 'number' => trim($legacy)];
            }
        }

        $phonesOut = array_map(function (array $p): array {
            return [
                'label' => $p['label'] !== '' ? $p['label'] : null,
                'number' => $p['number'],
                'href' => 'tel:'.$this->cleanPhone($p['number']),
            ];
        }, $phones);

        // سازگاری با مصرف‌کننده‌های قدیمی: phone = شماره‌ی اولین ردیف.
        $phone = $phonesOut[0]['number'] ?? null;

        return response()
            ->json([
                'site_name' => $values['site_name'] ?? null,
                'site_tagline' => $values['site_tagline'] ?? null,
                'site_logo_url' => $values['site_logo_url'] ?? null,

                // تماس
                'phone' => $phone,
                'phone_href' => $phone ? 'tel:'.$this->cleanPhone($phone) : null,
                'phones' => $phonesOut,
                'support_phone' => $values['contact_support_phone'] ?? null,
                'email' => $values['contact_email'] ?? null,
                'address' => $values['contact_address'] ?? null,
                'working_hours' => $values['contact_working_hours'] ?? null,

                // CTA پیش‌فرض (برای hero و header)
                'order_url' => $values['order_url'] ?? '/order',
                'order_label' => $values['order_label'] ?? 'ثبت سفارش',

                // شبکه‌های اجتماعی
                'social' => [
                    'instagram' => $values['social_instagram'] ?? null,
                    'telegram' => $values['social_telegram'] ?? null,
                    'whatsapp' => $values['social_whatsapp'] ?? null,
                    'youtube' => $values['social_youtube'] ?? null,
                    'linkedin' => $values['social_linkedin'] ?? null,
                    'aparat' => $values['social_aparat'] ?? null,
                ],
            ])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    /**
     * تبدیل JSON ذخیره‌شده‌ی contact_phones به آرایه‌ای از ['label'=>..,'number'=>..].
     *
     * @return array<int, array{label: string, number: string}>
     */
    private function decodePhones(?string $json): array
    {
        if (! is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return [];
        }

        $phones = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $number = trim((string) ($row['number'] ?? ''));
            if ($number === '') {
                continue;
            }
            $phones[] = [
                'label' => trim((string) ($row['label'] ?? '')),
                'number' => $number,
            ];
        }

        return $phones;
    }

    /**
     * تبدیل شماره به فرمت قابل استفاده در href=tel:
     */
    private function cleanPhone(string $phone): string
    {
        return preg_replace('/[^\d+]/', '', $phone) ?? $phone;
    }
}
