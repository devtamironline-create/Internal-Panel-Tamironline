<?php

namespace Modules\Seo\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Seo\Models\SeoSetting;

/**
 * GET /v1/seo/settings
 *
 * تنظیمات سراسری سئو برای فرانت (جداکننده، نام/توضیح سایت، پیش‌فرض‌های
 * اجتماعی، کدهای verification، و knowledge graph).
 */
class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $payload = [
            'data' => [
                'separator' => SeoSetting::get('separator') ?: (string) config('seo.separator', '–'),
                'site_name' => SeoSetting::get('site_name') ?: (string) config('app.name'),
                'site_description' => SeoSetting::get('site_description'),
                'canonical_base_url' => rtrim((string) (SeoSetting::get('canonical_base_url') ?: config('app.url')), '/'),
                'social' => [
                    'og_default_image' => SeoSetting::get('og_default_image'),
                    'twitter_card' => SeoSetting::get('twitter_card') ?: 'summary_large_image',
                    'twitter_site' => SeoSetting::get('twitter_site'),
                    'twitter_creator' => SeoSetting::get('twitter_creator'),
                    'facebook_app_id' => SeoSetting::get('facebook_app_id'),
                ],
                'verification' => SeoSetting::group('verification'),
                'integrations' => [
                    'ga4_measurement_id' => SeoSetting::get('ga4_measurement_id'),
                    'gtm_container_id' => SeoSetting::get('gtm_container_id'),
                    'analytics_disable_for_admins' => SeoSetting::get('analytics_disable_for_admins') === '1',
                ],
                'knowledge_graph' => [
                    'type' => SeoSetting::get('kg_type') ?: 'Organization',
                    'name' => SeoSetting::get('kg_name') ?: (SeoSetting::get('site_name') ?: config('app.name')),
                    'logo' => SeoSetting::get('kg_logo'),
                    'same_as' => SeoSetting::getJson('kg_same_as', []),
                ],
            ],
        ];

        $etag = '"'.md5(json_encode($payload, JSON_UNESCAPED_UNICODE)).'"';
        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response()->json(null, 304)->setEtag(trim($etag, '"'));
        }

        return response()->json($payload)
            ->setEtag(trim($etag, '"'))
            ->header('Cache-Control', 'public, max-age=300, s-maxage=300, stale-while-revalidate=600');
    }
}
