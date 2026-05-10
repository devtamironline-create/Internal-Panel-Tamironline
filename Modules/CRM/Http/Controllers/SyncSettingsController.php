<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\CRM\Models\CrmSetting;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * تنظیمات سینک با CRM وردپرسی — مدیریت توکن Bearer + دانلود پلاگین.
 */
class SyncSettingsController extends Controller
{
    /** مسیر فایل پلاگین وردپرس روی سرور (نسبی به ریشهٔ پروژه). */
    private const PLUGIN_ZIP_RELATIVE = 'wp-sync-plugin/tamironline-crm-sync.zip';
    private const PLUGIN_PHP_RELATIVE = 'wp-sync-plugin/tamironline-crm-sync/tamironline-crm-sync.php';

    public function index(): View
    {
        $token = CrmSetting::get('wp_sync_token');

        if (empty($token)) {
            $token = Str::random(64);
            CrmSetting::set('wp_sync_token', $token);
        }

        $zipPath = base_path(self::PLUGIN_ZIP_RELATIVE);
        $available = file_exists($zipPath);

        return view('crm::sync.settings', [
            'token' => $token,
            'baseUrl' => url('/api/crm/sync'),
            'pingUrl' => route('crm.sync.ping'),
            'pluginVersion' => $this->readPluginVersion(),
            'pluginAvailable' => $available,
            'pluginSize' => $available ? filesize($zipPath) : null,
            'pluginMtime' => $available ? filemtime($zipPath) : null,
            'pluginSha1' => $available ? sha1_file($zipPath) : null,

            // سینک معکوس (Laravel → WP)
            'wpPushEnabled' => CrmSetting::get('wp_push_enabled') === '1',
            'wpPushUrl'     => CrmSetting::get('wp_push_url') ?? '',
            'wpPushSecret'  => CrmSetting::get('wp_push_secret') ?? '',
        ]);
    }

    /**
     * ذخیرهٔ تنظیمات سینک معکوس Laravel → WP.
     * URL سایت WP + secret مشترک HMAC + on/off.
     */
    public function updateWpPush(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'wp_push_url' => 'nullable|url|max:255',
            'wp_push_secret' => 'nullable|string|min:16|max:128',
            'wp_push_enabled' => 'nullable|boolean',
        ], [
            'wp_push_url.url' => 'آدرس سایت وردپرس معتبر نیست.',
            'wp_push_secret.min' => 'کلید مشترک باید حداقل ۱۶ کاراکتر باشد.',
        ]);

        CrmSetting::set('wp_push_url', $validated['wp_push_url'] ?? '');
        CrmSetting::set('wp_push_secret', $validated['wp_push_secret'] ?? '');
        CrmSetting::set('wp_push_enabled', ! empty($validated['wp_push_enabled']) ? '1' : '0');

        return back()->with('success', 'تنظیمات سینک معکوس ذخیره شد.');
    }

    public function regenerate(): RedirectResponse
    {
        $token = Str::random(64);
        CrmSetting::set('wp_sync_token', $token);

        return back()->with('success', 'توکن جدید ساخته شد. لطفاً آن را در پلاگین وردپرس به‌روزرسانی کنید.');
    }

    public function downloadPlugin(): BinaryFileResponse
    {
        $path = base_path(self::PLUGIN_ZIP_RELATIVE);
        abort_unless(file_exists($path), 404, 'فایل پلاگین یافت نشد.');

        $version = $this->readPluginVersion();
        // sha1 کوتاه در نام فایل تا cache مرورگر/CDN فایل قدیمی را نگه ندارد
        $shortHash = substr(sha1_file($path), 0, 7);
        $filename = 'tamironline-crm-sync' . ($version ? '-' . $version : '') . '-' . $shortHash . '.zip';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /** خواندن نسخه از header پلاگین (Version: X.Y.Z). */
    protected function readPluginVersion(): ?string
    {
        $php = base_path(self::PLUGIN_PHP_RELATIVE);
        if (! file_exists($php)) {
            return null;
        }
        $head = file_get_contents($php, false, null, 0, 1024);
        if ($head === false) {
            return null;
        }

        return preg_match('/Version:\s*([\d.]+)/', $head, $m) ? $m[1] : null;
    }
}
