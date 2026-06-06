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
        'tech_panel_default_avatar',
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
            'tech_panel_default_avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
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

    /**
     * سرو فایل برند (لوگو/بنر/Hero) از طریق PHP — جایگزین asset('storage/...')
     * که روی هاست‌های cPanel/LiteSpeed بدون symlink ۴۰۴ می‌شود. این روت
     * عمومی است (بدون auth) چون این تصاویر در صفحهٔ ورود تکنسین استفاده
     * می‌شوند که خودش نیاز به ورود ندارد.
     */
    public function serve(string $key)
    {
        if (! in_array($key, self::IMAGE_KEYS, true)) {
            abort(404);
        }

        $path = Setting::get($key);
        if (! $path || ! Storage::disk('public')->exists($path)) {
            // تصویر فیزیکی موجود نیست (یا تنظیمات خالی است، یا فایل
            // در حادثهٔ pull برانچ deploy/ganje پاک شده). به‌جای 404،
            // یک SVG خنثی برمی‌گردانیم تا UI شکسته نباشد. ادمین می‌تواند
            // از صفحهٔ تنظیمات پنل تکنسین تصویر را دوباره آپلود کند.
            return self::placeholderResponse($key);
        }

        return Storage::disk('public')->response(
            $path,
            basename($path),
            [
                'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }

    /**
     * SVG placeholder که جای تصویر گم‌شده نمایش داده می‌شود.
     * بسته به نوع کلید (hero/banner/logo/avatar) رنگ و آیکن متناسب.
     */
    public static function placeholderResponse(string $key)
    {
        $isAvatar = str_contains($key, 'avatar');
        $bg = $isAvatar ? '#e0e7ff' : '#dbeafe';
        $fg = $isAvatar ? '#6366f1' : '#3b82f6';
        $iconPath = $isAvatar
            ? 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'
            : 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z';

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" preserveAspectRatio="xMidYMid meet">
    <rect width="200" height="200" fill="{$bg}"/>
    <g transform="translate(76 76) scale(2)" fill="none" stroke="{$fg}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="{$iconPath}"/>
    </g>
</svg>
SVG;

        return response($svg, 200, [
            'Content-Type'  => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }
}
