<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\CRM\Models\CrmSetting;

/**
 * صفحهٔ «تنظیماتِ عمومی» — فقط فلگ‌های مشخص و امنِ crm_settings را نشان
 * می‌دهد و قابلِ تغییر می‌کند (نه ویرایشگرِ خامِ همهٔ کلیدها). هر فلگ با
 * برچسب/توضیح و نوعِ ورودی تعریف می‌شود تا تغییر بی‌خطر باشد.
 */
class FeatureFlagsController extends Controller
{
    /**
     * فهرستِ فلگ‌های قابلِ نمایش/تغییر. برای افزودنِ کلیدِ جدید، فقط یک ردیف
     * این‌جا اضافه کنید (type: toggle | text).
     *
     * @return array<int, array<string, mixed>>
     */
    private function flags(): array
    {
        return [
            [
                'key' => 'tech_proforma_enabled',
                'label' => 'نمایش «پیش‌فاکتور» در پنل تکنسین',
                'help' => 'خاموش = سیستم پیش‌فاکتور در اپ/پنل تکنسین مخفی و مسیرهایش بسته است. روشن = فعال.',
                'type' => 'toggle',
                'default' => '0',
            ],
            [
                'key' => 'tech_panel_readonly',
                'label' => 'حالت فقط‌خواندنی پنل تکنسین (فریز)',
                'help' => 'روشن = نهایی‌سازی سفارش (پایان/کنسل/رد/ایاب‌وذهاب) در پنل تکنسین موقتاً غیرفعال می‌شود.',
                'type' => 'toggle',
                'default' => '0',
            ],
            [
                'key' => 'proforma_public_url_template',
                'label' => 'قالب لینک عمومی پیش‌فاکتور',
                'help' => 'مثلاً https://app.tamironline.com/proforma/{token} — خالی یعنی به صفحهٔ رسیدِ پنل می‌رود. {token} حتماً باید در آدرس باشد.',
                'type' => 'text',
                'default' => '',
                'placeholder' => 'https://app.tamironline.com/proforma/{token}',
            ],
            [
                'key' => 'transfer_receipt_enabled',
                'label' => 'رسید انتقال (ادمین + تکنسین)',
                'help' => 'روشن = ثبت رسید انتقال ممکن است و لینکش با پیامک برای مشتری می‌رود. خاموش = ثبت رسید مخفی و بسته است.',
                'type' => 'toggle',
                'default' => '1',
            ],
            [
                'key' => 'transfer_receipt_url_template',
                'label' => 'قالب لینک عمومی رسید انتقال',
                'help' => 'مثلاً https://app.tamironline.com/transfer-receipt/{token} — خالی یعنی به صفحهٔ رسیدِ پنل می‌رود. {token} حتماً باید در آدرس باشد.',
                'type' => 'text',
                'default' => '',
                'placeholder' => 'https://app.tamironline.com/transfer-receipt/{token}',
            ],
        ];
    }

    public function index(): View
    {
        $flags = collect($this->flags())->map(function ($f) {
            $f['value'] = CrmSetting::get($f['key'], $f['default'] ?? null);

            return $f;
        })->all();

        return view('crm::settings.feature-flags', ['flags' => $flags]);
    }

    public function update(Request $request): RedirectResponse
    {
        foreach ($this->flags() as $flag) {
            $key = $flag['key'];

            if (($flag['type'] ?? 'text') === 'toggle') {
                CrmSetting::set($key, $request->boolean($key) ? '1' : '0');

                continue;
            }

            // text — تریم؛ خالی → null (پاک‌کردن مقدار).
            $value = trim((string) $request->input($key, ''));
            CrmSetting::set($key, $value !== '' ? mb_substr($value, 0, 500) : null);
        }

        return back()->with('success', 'تنظیمات ذخیره شد.');
    }
}
