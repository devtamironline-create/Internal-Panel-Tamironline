<?php

namespace Modules\Seo\Http\Controllers\Api;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Seo\Models\SeoSetting;

/**
 * محتوای robots.txt — از پنل قابل ویرایش (کلید robots_txt). اگر خالی باشد،
 * یک نسخهٔ پیش‌فرض با ارجاع به sitemap index تولید می‌شود.
 */
class RobotsController extends Controller
{
    public function show(): Response
    {
        $base = SeoSetting::siteUrl();
        $custom = trim((string) SeoSetting::get('robots_txt'));

        // آدرسِ عمومیِ سایت‌مپ (فرانت /sitemap.xml را از بک‌اند پروکسی می‌کند) —
        // نه مسیرِ داخلیِ /v1/seo/… که برای گوگل کاربردی ندارد.
        $body = $custom !== '' ? $custom : implode("\n", [
            'User-agent: *',
            'Allow: /',
            '',
            'Sitemap: '.$base.'/sitemap.xml',
        ]);

        // تضمین وجود دایرکتیو Sitemap حتی اگر ادمین فراموش کرده باشد.
        if (! str_contains($body, 'Sitemap:')) {
            $body .= "\n\nSitemap: ".$base.'/sitemap.xml';
        }

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600, s-maxage=3600',
        ]);
    }
}
