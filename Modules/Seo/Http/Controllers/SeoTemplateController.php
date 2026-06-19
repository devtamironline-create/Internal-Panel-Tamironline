<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Services\SeoableRegistry;

/**
 * قالب‌های عنوان/توضیح به‌ازای هر نوع صفحه (+ سراسری).
 *
 * فقط تنظیماتِ tpl_{type}_{field} را می‌نویسد؛ خوانش و سطح‌بندی resolver
 * در MetaResolver انجام می‌شود:
 *   override آیتم ← tpl_{type}_{field} ← tpl_global_{field} ← config.
 */
class SeoTemplateController extends Controller
{
    /** برچسب فارسیِ هر نوع برای نمایش در پنل. */
    private const TYPE_LABELS = [
        'global' => 'پیش‌فرضِ سراسری',
        'page' => 'صفحهٔ ثابت',
        'article' => 'مقاله',
        'blog_topic' => 'دستهٔ بلاگ',
        'brand' => 'برند',
        'device' => 'دستگاه',
        'brand_device' => 'برند+دستگاه',
        'taxonomy' => 'دستهٔ سؤالات',
        'forum_question' => 'پرسش فروم',
    ];

    /** متغیرهای راهنما (key بدون توکن → برچسب فارسی). */
    private const VARIABLES = [
        'title' => 'عنوان آیتم',
        'slug' => 'نشانهٔ صفحه',
        'sitename' => 'نام سایت',
        'site_name' => 'نام سایت',
        'sitedesc' => 'توضیح سایت',
        'sep' => 'جداکننده',
        'excerpt' => 'خلاصه/چکیده',
        'brand' => 'نام برند',
        'device' => 'نام دستگاه',
        'city' => 'شهر',
        'guarantee' => 'متن ضمانت',
        'phone' => 'تلفن',
        'date' => 'تاریخ انتشار',
        'currentyear' => 'سال جاری',
        'year' => 'سال',
        'id' => 'شناسهٔ آیتم',
    ];

    public function edit(SeoableRegistry $registry)
    {
        $types = array_merge(['global'], array_keys($registry->all()));

        $rows = [];
        foreach ($types as $type) {
            $rows[$type] = [
                'label' => self::TYPE_LABELS[$type] ?? $type,
                'title' => SeoSetting::get("tpl_{$type}_title")
                    ?: config("seo.templates.$type.title", ''),
                'description' => SeoSetting::get("tpl_{$type}_description")
                    ?: config("seo.templates.$type.description", ''),
            ];
        }

        $variables = self::VARIABLES;

        return view('seo::templates.index', compact('rows', 'variables'));
    }

    public function update(Request $request, SeoableRegistry $registry)
    {
        $request->validate([
            'templates' => 'required|array',
            'templates.*.title' => 'nullable|string|max:255',
            'templates.*.description' => 'nullable|string|max:500',
        ]);

        $allowed = array_merge(['global'], array_keys($registry->all()));
        $templates = (array) $request->input('templates', []);

        foreach ($templates as $type => $fields) {
            if (! in_array($type, $allowed, true)) {
                continue;
            }

            $title = mb_substr(trim((string) ($fields['title'] ?? '')), 0, 255);
            $description = mb_substr(trim((string) ($fields['description'] ?? '')), 0, 500);

            SeoSetting::set("tpl_{$type}_title", $title, 'templates');
            SeoSetting::set("tpl_{$type}_description", $description, 'templates');
        }

        SeoChangeLog::record('updated', 'seo_templates', 'قالب‌های عنوان/توضیح سئو به‌روزرسانی شد.');

        return back()->with('success', 'قالب‌های سئو ذخیره شد.');
    }
}
