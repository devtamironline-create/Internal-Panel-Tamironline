<?php

namespace Modules\CRM\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * تنظیمات ظاهری پنل تکنسین (PWA): لوگو/آیکن، بنر داشبورد، عکس صفحه
 * لاگین، نام نمایشی، شماره پشتیبانی.
 *
 * این مقادیر همان کلیدهایی هستند که CrmServiceProvider via View::composer
 * به همهٔ ویوهای crm::tech-panel.* تزریق می‌کند (brandLogo / brandBanner
 * / brandHero / appName / supportPhone).
 */
class TechPanelSettingsController extends Controller
{
    /** کلیدهای تنظیمات قابل ویرایش از این صفحه. */
    private const IMAGE_KEYS = [
        'tech_panel_logo',
        'tech_panel_banner',
        'tech_panel_hero',
    ];

    private const TEXT_KEYS = [
        'tech_panel_name',
        'tech_panel_support_phone',
    ];

    public function index()
    {
        $settings = Setting::getMany(array_merge(self::IMAGE_KEYS, self::TEXT_KEYS));

        return view('crm::tech-panel-settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        // تشخیص case ای که PHP به علت post_max_size درخواست را ساکت دور
        // ریخته (هیچ POST و FILES نمی‌رسد ولی Content-Length بزرگ بود).
        // در این حالت Laravel استثنا نمی‌دهد و user فکر می‌کند ذخیره شد.
        if (
            $request->server('CONTENT_LENGTH') > 0
            && empty($_POST)
            && empty($_FILES)
        ) {
            $limit = ini_get('post_max_size') ?: '8M';
            return back()->withErrors([
                'upload' => "فایل ارسالی بزرگتر از سقف PHP ({$limit}) بود و سرور آن را قبول نکرد. حجم تصویر را کاهش دهید یا با هاست برای افزایش post_max_size تماس بگیرید.",
            ]);
        }

        $validated = $request->validate([
            'tech_panel_name' => 'nullable|string|max:100',
            'tech_panel_support_phone' => 'nullable|string|max:30',
            'tech_panel_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'tech_panel_banner' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'tech_panel_hero' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        // متن‌ها — فقط اگر در این فرم خاص ارسال شده‌اند (هر فرم
        // مستقل است؛ فرم تصویر نباید متن قدیمی را خراب کند).
        foreach (self::TEXT_KEYS as $key) {
            if ($request->has($key) && array_key_exists($key, $validated)) {
                Setting::set($key, $validated[$key]);
            }
        }

        // تصاویر — فقط در صورت آپلود فایل جدید عوض می‌شوند؛ فایل قبلی پاک می‌شود.
        $uploadedKey = null;
        foreach (self::IMAGE_KEYS as $key) {
            if ($request->hasFile($key)) {
                $old = Setting::get($key);
                if ($old) {
                    Storage::disk('public')->delete($old);
                }
                $path = $request->file($key)->store('tech-panel', 'public');
                Setting::set($key, $path);
                $uploadedKey = $key;
            }
        }

        $message = $uploadedKey
            ? 'تصویر با موفقیت آپلود شد.'
            : 'تنظیمات پنل تکنسین ذخیره شد.';

        return redirect()
            ->route('crm.tech-panel-settings.index')
            ->with('success', $message);
    }

    public function deleteImage(string $key)
    {
        if (! in_array($key, self::IMAGE_KEYS, true)) {
            abort(404);
        }

        $current = Setting::get($key);
        if ($current) {
            Storage::disk('public')->delete($current);
            Setting::set($key, null);
        }

        return redirect()
            ->route('crm.tech-panel-settings.index')
            ->with('success', 'تصویر حذف شد.');
    }
}
