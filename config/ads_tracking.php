<?php

/*
|--------------------------------------------------------------------------
| Server-side Google Ads Call Tracking — پایهٔ Backend
|--------------------------------------------------------------------------
| دیتابیسِ تعمیرآنلاین Source of Truth است: ثبتِ Call Click به هیچ سرویسِ
| بیرونی (از جمله Google) وابسته نیست. آپلود به Google در این مرحله عمداً
| خاموش است و فقط فیلدها/وضعیت‌ها آماده‌اند.
*/

return [
    'enabled' => (bool) env('ADS_TRACKING_ENABLED', true),

    // originهای مجاز برای CORS دو endpoint عمومی — با کاما جدا.
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'ADS_TRACKING_ORIGINS',
        'https://tamironline.com,https://www.tamironline.com,https://app.tamironline.com'
    ))))),

    // طولِ عمرِ attribution (روز) — بعد از آن منقضی محسوب می‌شود؛ در
    // business logic هاردکد نشده است.
    'attribution_ttl_days' => (int) env('ADS_TRACKING_ATTRIBUTION_TTL_DAYS', 90),

    // مرحلهٔ بعد — الان به هیچ عنوان روشن نشود.
    'google_upload_enabled' => (bool) env('ADS_TRACKING_GOOGLE_UPLOAD', false),
];
