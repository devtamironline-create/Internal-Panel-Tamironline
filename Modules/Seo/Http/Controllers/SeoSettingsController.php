<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Seo\Models\SeoSetting;

/**
 * تنظیمات سراسری سئو (پیش‌فرض‌ها، شبکه‌های اجتماعی، verification، knowledge graph).
 */
class SeoSettingsController extends Controller
{
    /** نگاشت کلید فرم → گروه ذخیره‌سازی. */
    private const KEYS = [
        // general
        'site_name' => 'general',
        'site_description' => 'general',
        'separator' => 'general',
        'canonical_base_url' => 'general',
        // social
        'og_default_image' => 'social',
        'twitter_card' => 'social',
        'twitter_site' => 'social',
        'twitter_creator' => 'social',
        'facebook_app_id' => 'social',
        // verification
        'verify_google' => 'verification',
        'verify_bing' => 'verification',
        'verify_yandex' => 'verification',
        'verify_baidu' => 'verification',
        'verify_pinterest' => 'verification',
        // knowledge graph
        'kg_type' => 'knowledge_graph',
        'kg_name' => 'knowledge_graph',
        'kg_logo' => 'knowledge_graph',
    ];

    public function index()
    {
        $settings = SeoSetting::query()->pluck('value', 'key')->all();
        $sameAs = SeoSetting::getJson('kg_same_as', []);

        return view('seo::settings.index', compact('settings', 'sameAs'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'site_name' => 'nullable|string|max:255',
            'site_description' => 'nullable|string|max:500',
            'separator' => 'nullable|string|max:10',
            'canonical_base_url' => 'nullable|string|max:255',
            'og_default_image' => 'nullable|string|max:500',
            'twitter_card' => 'nullable|in:summary,summary_large_image,app,player',
            'twitter_site' => 'nullable|string|max:100',
            'twitter_creator' => 'nullable|string|max:100',
            'facebook_app_id' => 'nullable|string|max:100',
            'verify_google' => 'nullable|string|max:255',
            'verify_bing' => 'nullable|string|max:255',
            'verify_yandex' => 'nullable|string|max:255',
            'verify_baidu' => 'nullable|string|max:255',
            'verify_pinterest' => 'nullable|string|max:255',
            'kg_type' => 'nullable|in:Organization,Person',
            'kg_name' => 'nullable|string|max:255',
            'kg_logo' => 'nullable|string|max:500',
            'kg_same_as' => 'nullable|array',
            'kg_same_as.*' => 'nullable|string|max:255',
        ]);

        foreach (self::KEYS as $key => $group) {
            SeoSetting::set($key, $validated[$key] ?? null, $group);
        }

        $sameAs = array_values(array_filter(array_map('trim', (array) $request->input('kg_same_as', []))));
        SeoSetting::setJson('kg_same_as', $sameAs, 'knowledge_graph');

        return back()->with('success', 'تنظیمات سئو ذخیره شد.');
    }
}
