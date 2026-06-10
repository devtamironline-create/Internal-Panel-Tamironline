<?php

/**
 * تنظیمات سراسری وضعیت اپلیکیشن موبایل/PWA.
 *
 * توسط GET /v1/customer/status برگردانده می‌شود تا فرانت بتواند:
 *   - overlay maintenance یا server_down بزند
 *   - overlay outdated_version بزند اگر app_version < min_version
 *   - logout اجباری همه‌ی توکن‌ها با تغییر force_reauth_at
 *
 * هر کلید از env قابل تغییر است — برای maintenance بدون redeploy کافیست
 * مقدار env را تغییر داد و cache config پاک شود.
 */
return [

    /**
     * اگر true شود، اپ overlay سراسری «سرویس غیرفعال است» نمایش می‌دهد.
     * هیچ endpointی هم به‌جای 200 می‌تواند 503 بدهد (برای محکم‌کاری).
     */
    'app_disabled' => (bool) env('APP_DISABLED', false),

    /**
     * کمترین نسخه‌ی کلاینت مجاز. نسخه‌ی پایین‌تر → overlay اجباری به‌روزرسانی.
     * مقدار semver.
     */
    'min_version' => (string) env('APP_MIN_VERSION', '1.0.0'),

    /**
     * آخرین نسخه‌ی موجود اپ. برای banner نرم/اختیاری به‌روزرسانی.
     */
    'latest_version' => (string) env('APP_LATEST_VERSION', '1.0.0'),

    /**
     * Unix timestamp. هر توکنی که `iat` آن قبل از این مقدار است،
     * در /v1/customer/status اپ مجبور به logout می‌شود.
     *
     * استفاده: اگر مشکل امنیتی پیدا شد → این عدد را به now() ست کن →
     * همه‌ی توکن‌های موجود بی‌اعتبار می‌شوند (logout اجباری بدون نیاز به
     * blacklist DB).
     */
    'force_reauth_at' => (int) env('APP_FORCE_REAUTH_AT', 0),

    /**
     * حالت maintenance قابل اعلام به فرانت — overlay مودال متن دار.
     */
    'maintenance' => [
        'active' => (bool) env('APP_MAINTENANCE_ACTIVE', false),
        'message' => env('APP_MAINTENANCE_MESSAGE'),
    ],

];
