<?php

namespace Modules\Seo\Http\Controllers\Api;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\Seo\Models\SeoSetting;

/**
 * محتوای llms.txt — از پنل قابل ویرایش (کلید llms_txt). اگر خالی باشد، یک
 * نسخهٔ پیش‌فرض از روی نام/توضیح سایت تولید می‌شود.
 */
class LlmsController extends Controller
{
    public function show(): Response
    {
        $custom = trim((string) SeoSetting::get('llms_txt'));

        if ($custom !== '') {
            $body = $custom;
        } else {
            $siteName = SeoSetting::get('site_name') ?: (string) config('app.name');
            $desc = trim((string) SeoSetting::get('site_description'));

            // فرمتِ استانداردِ llms.txt (llmstxt.org): H1 + خلاصهٔ blockquote +
            // بخشی از لینک‌های Markdownِ مطلق. لینک‌ها باید [متن](url) باشند تا
            // ابزارهای اعتبارسنجی «هیچ لینکی یافت نشد» گزارش ندهند.
            $base = SeoSetting::siteUrl();

            $body = "# {$siteName}\n\n";
            if ($desc !== '') {
                $body .= "> {$desc}\n\n";
            }
            $body .= "## Important pages\n\n"
                ."- [خدمات تعمیرات]({$base}/services): فهرست خدمات و تعرفهٔ تعمیرات\n"
                ."- [وبلاگ]({$base}/blog): مقالات و راهنمای تعمیرات\n"
                ."- [صفحهٔ اصلی]({$base}/): معرفی تعمیرآنلاین\n";

            // لینک‌های واقعیِ دستگاه‌ها و برندهای فعال — تا LLMها ساختارِ صفحاتِ
            // خدمات را بشناسند. محدود و مرتب تا فایل قابل‌مدیریت بماند.
            $devices = Device::query()->where('is_active', true)
                ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('name')
                ->limit(60)->get(['name', 'slug']);
            if ($devices->isNotEmpty()) {
                $body .= "\n## دستگاه‌ها (Devices)\n\n";
                foreach ($devices as $d) {
                    $body .= "- [تعمیر {$d->name}]({$base}/services/{$d->slug})\n";
                }
            }

            $brands = Brand::query()->where('is_active', true)
                ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('name')
                ->limit(80)->get(['name', 'slug']);
            if ($brands->isNotEmpty()) {
                $body .= "\n## برندها (Brands)\n\n";
                foreach ($brands as $b) {
                    $body .= "- [خدمات {$b->name}]({$base}/brands/{$b->slug})\n";
                }
            }
        }

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600, s-maxage=3600',
        ]);
    }
}
