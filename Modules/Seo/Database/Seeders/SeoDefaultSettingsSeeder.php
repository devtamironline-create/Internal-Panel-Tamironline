<?php

namespace Modules\Seo\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Seo\Models\SeoSetting;

/**
 * مقادیر پیش‌فرضِ تنظیمات سراسری سئو. فقط کلیدهای خالی را پر می‌کند تا
 * اجرای دوباره مقادیر ادمین را بازنویسی نکند.
 */
class SeoDefaultSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'site_name', 'value' => (string) config('app.name'), 'group' => 'general'],
            ['key' => 'separator', 'value' => '–', 'group' => 'general'],
            // مبنای canonical = سایتِ اصلیِ فرانت (نه APP_URLِ پنل).
            ['key' => 'canonical_base_url', 'value' => rtrim((string) config('seo.site_url'), '/'), 'group' => 'general'],
            ['key' => 'twitter_card', 'value' => 'summary_large_image', 'group' => 'social'],
            ['key' => 'kg_type', 'value' => 'Organization', 'group' => 'knowledge_graph'],
            // §15 Facebook domain verification
            ['key' => 'verification_facebook', 'value' => '', 'group' => 'verification'],
            // §13 GA4 / GTM
            ['key' => 'ga4_measurement_id', 'value' => '', 'group' => 'integrations'],
            ['key' => 'gtm_container_id', 'value' => '', 'group' => 'integrations'],
            ['key' => 'analytics_disable_for_admins', 'value' => '', 'group' => 'integrations'],
            // §39 llms.txt
            ['key' => 'llms_txt', 'value' => '', 'group' => 'general'],
            // §38 FAQPage schema (پیش‌فرض غیرفعال)
            ['key' => 'faq_schema_enabled', 'value' => '', 'group' => 'general'],
        ];

        foreach ($defaults as $row) {
            if (SeoSetting::query()->where('key', $row['key'])->exists()) {
                continue;
            }
            SeoSetting::query()->create($row);
        }
    }
}
