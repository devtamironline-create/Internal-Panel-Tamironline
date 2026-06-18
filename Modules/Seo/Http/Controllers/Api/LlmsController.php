<?php

namespace Modules\Seo\Http\Controllers\Api;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
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

            $body = "# {$siteName}\n\n{$desc}\n\nImportant pages:\n- /services\n- /blog\n";
        }

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600, s-maxage=3600',
        ]);
    }
}
