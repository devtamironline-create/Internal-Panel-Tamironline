<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Seo\Models\SeoSetting;

/**
 * اصلاحِ canonical_base_url اگر اشتباهاً به پنل (APP_URL) اشاره می‌کند.
 *
 * seederِ قبلی این مقدار را روی config('app.url') (پنل) می‌گذاشت → مانیتورینگ
 * پنلِ auth-walled را کرال می‌کرد و sitemap/canonical لینکِ پنل می‌داد. اینجا
 * فقط در صورتی که مقدار خالی باشد یا host آن با hostِ پنل یکی باشد، آن را به
 * سایتِ اصلیِ فرانت (config('seo.site_url') = FRONTEND_URL) تغییر می‌دهیم.
 * اگر اپراتور قبلاً مقدارِ درستِ فرانت را گذاشته، دست‌نخورده می‌ماند.
 */
return new class extends Migration
{
    public function up(): void
    {
        $frontend = rtrim((string) config('seo.site_url'), '/');
        if ($frontend === '') {
            return;
        }

        $current = trim((string) SeoSetting::get('canonical_base_url', ''));
        $panelHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $currentHost = $current !== '' ? strtolower((string) parse_url($current, PHP_URL_HOST)) : '';

        // فقط وقتی خالی است یا به hostِ پنل اشاره می‌کند، اصلاح کن.
        if ($current === '' || ($panelHost !== '' && $currentHost === $panelHost)) {
            SeoSetting::set('canonical_base_url', $frontend, 'general');
        }
    }

    public function down(): void
    {
        // برگشت‌پذیر نیست (مقدارِ قبلی نگه داشته نمی‌شود) — no-op.
    }
};
