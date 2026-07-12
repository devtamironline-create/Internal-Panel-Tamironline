<?php

namespace Modules\CustomerApp\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * تنظیمات runtime اپلیکیشن مشتریان.
 *
 * مقادیر در جدول app_settings (key/value) ذخیره می‌شوند تا بدون redeploy
 * قابل تغییر باشند. config/app_status.php همچنان به‌عنوان fallback پیش‌فرض
 * استفاده می‌شود.
 *
 * هر تغییر cache مربوطه را پاک می‌کند (Setting::set خودش این کار را
 * انجام می‌دهد).
 */
class AppSettingsController extends Controller
{
    private const KEYS = [
        'app_disabled' => false,
        'app_maintenance_active' => false,
        'app_maintenance_message' => null,
        'app_min_version' => '1.0.0',
        'app_latest_version' => '1.0.0',
        'app_force_reauth_at' => 0,
    ];

    public function index(): View
    {
        $values = $this->currentValues();

        return view('customerapp::admin.settings', compact('values'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'app_disabled' => 'nullable|boolean',
            'app_maintenance_active' => 'nullable|boolean',
            'app_maintenance_message' => 'nullable|string|max:500',
            'app_min_version' => 'required|string|regex:/^\d+\.\d+\.\d+$/',
            'app_latest_version' => 'required|string|regex:/^\d+\.\d+\.\d+$/',
        ]);

        Setting::set('app_disabled', (bool) ($data['app_disabled'] ?? false) ? '1' : '0');
        Setting::set('app_maintenance_active', (bool) ($data['app_maintenance_active'] ?? false) ? '1' : '0');
        Setting::set('app_maintenance_message', $data['app_maintenance_message'] ?? '');
        Setting::set('app_min_version', trim($data['app_min_version']));
        Setting::set('app_latest_version', trim($data['app_latest_version']));

        return redirect()->route('customer-app.settings.index')
            ->with('success', 'تنظیمات ذخیره شد.');
    }

    /**
     * تنظیم force_reauth_at به الان — همه‌ی توکن‌های قدیمی‌تر بی‌اعتبار
     * می‌شوند (اپ فرانت در /status متوجه می‌شود و logout می‌کند).
     */
    public function forceReauth(Request $request): RedirectResponse
    {
        $ts = time();
        Setting::set('app_force_reauth_at', (string) $ts);

        return redirect()->route('customer-app.settings.index')
            ->with('success', 'force_reauth_at به الان ست شد. همه‌ی توکن‌های قدیمی در باز شدن بعدی اپ بی‌اعتبار خواهند بود.');
    }

    /**
     * پاک‌کردنِ کشِ اپ — نسخهٔ کش را bump می‌کند تا اپ در poll بعدیِ /status
     * کشِ کاتالوگ/برند/بنر/استان‌وشهر را باطل و داده‌ی تازه بگیرد.
     */
    public function purgeCache(Request $request): RedirectResponse
    {
        \Modules\CustomerApp\Support\AppCacheVersion::bump();

        return redirect()->route('customer-app.settings.index')
            ->with('success', 'کشِ اپ باطل شد. اپ‌ها ظرفِ حدود ۶۰ ثانیه (یک چرخهٔ poll) داده‌ی تازه می‌گیرند.');
    }

    /**
     * @return array<string, mixed>
     */
    private function currentValues(): array
    {
        $out = [];
        foreach (self::KEYS as $key => $default) {
            $configDefault = match ($key) {
                'app_disabled' => config('app_status.app_disabled', $default),
                'app_maintenance_active' => config('app_status.maintenance.active', $default),
                'app_maintenance_message' => config('app_status.maintenance.message', $default),
                'app_min_version' => config('app_status.min_version', $default),
                'app_latest_version' => config('app_status.latest_version', $default),
                'app_force_reauth_at' => config('app_status.force_reauth_at', $default),
                default => $default,
            };
            $out[$key] = Setting::get($key, $configDefault);
            // تبدیل '0'/'1' به boolean برای فیلدهای bool
            if (in_array($key, ['app_disabled', 'app_maintenance_active'], true)) {
                $out[$key] = (bool) $out[$key];
            }
            if ($key === 'app_force_reauth_at') {
                $out[$key] = (int) $out[$key];
            }
        }

        return $out;
    }
}
