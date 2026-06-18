<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Seo\Models\SeoChangeLog;
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
        'publish_allow_seo_errors' => 'general',
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
        'verification_facebook' => 'verification',
        // knowledge graph
        'kg_type' => 'knowledge_graph',
        'kg_name' => 'knowledge_graph',
        'kg_logo' => 'knowledge_graph',
        // local business (Local SEO)
        'lb_enabled' => 'local',
        'lb_type' => 'local',
        'lb_name' => 'local',
        'lb_phone' => 'local',
        'lb_price_range' => 'local',
        'lb_street' => 'local',
        'lb_city' => 'local',
        'lb_region' => 'local',
        'lb_postal' => 'local',
        'lb_country' => 'local',
        'lb_lat' => 'local',
        'lb_lng' => 'local',
        // technical
        'robots_txt' => 'technical',
        // monitoring
        'alert_email' => 'monitoring',
        'alert_webhook' => 'monitoring',
        'pagespeed_api_key' => 'monitoring',
        // integrations
        'indexnow_key' => 'integrations',
        'ga4_measurement_id' => 'integrations',
        'gtm_container_id' => 'integrations',
        'analytics_disable_for_admins' => 'integrations',
        'gsc_property' => 'integrations',
        // llms.txt (general)
        'llms_txt' => 'general',
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
            'publish_allow_seo_errors' => 'nullable|in:1',
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
            'verification_facebook' => 'nullable|string|max:255',
            'kg_type' => 'nullable|in:Organization,Person',
            'kg_name' => 'nullable|string|max:255',
            'kg_logo' => 'nullable|string|max:500',
            'kg_same_as' => 'nullable|array',
            'kg_same_as.*' => 'nullable|string|max:255',
            'lb_enabled' => 'nullable|in:1',
            'lb_type' => 'nullable|string|max:50',
            'lb_name' => 'nullable|string|max:255',
            'lb_phone' => 'nullable|string|max:50',
            'lb_price_range' => 'nullable|string|max:20',
            'lb_street' => 'nullable|string|max:255',
            'lb_city' => 'nullable|string|max:100',
            'lb_region' => 'nullable|string|max:100',
            'lb_postal' => 'nullable|string|max:20',
            'lb_country' => 'nullable|string|max:2',
            'lb_lat' => 'nullable|string|max:30',
            'lb_lng' => 'nullable|string|max:30',
            'robots_txt' => 'nullable|string|max:20000',
            'alert_email' => 'nullable|email|max:255',
            'alert_webhook' => 'nullable|url|max:500',
            'pagespeed_api_key' => 'nullable|string|max:255',
            'indexnow_key' => 'nullable|string|max:255',
            'ga4_measurement_id' => 'nullable|string|max:50',
            'gtm_container_id' => 'nullable|string|max:50',
            'analytics_disable_for_admins' => 'nullable|in:1',
            'gsc_property' => 'nullable|string|max:255',
            'llms_txt' => 'nullable|string|max:20000',
        ]);

        foreach (self::KEYS as $key => $group) {
            SeoSetting::set($key, $validated[$key] ?? null, $group);
        }

        $sameAs = array_values(array_filter(array_map('trim', (array) $request->input('kg_same_as', []))));
        SeoSetting::setJson('kg_same_as', $sameAs, 'knowledge_graph');

        SeoChangeLog::record('updated', 'settings', 'تنظیمات سراسری سئو به‌روزرسانی شد.');

        return back()->with('success', 'تنظیمات سئو ذخیره شد.');
    }
}
