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
            ['key' => 'canonical_base_url', 'value' => rtrim((string) config('app.url'), '/'), 'group' => 'general'],
            ['key' => 'twitter_card', 'value' => 'summary_large_image', 'group' => 'social'],
            ['key' => 'kg_type', 'value' => 'Organization', 'group' => 'knowledge_graph'],
        ];

        foreach ($defaults as $row) {
            if (SeoSetting::query()->where('key', $row['key'])->exists()) {
                continue;
            }
            SeoSetting::query()->create($row);
        }
    }
}
