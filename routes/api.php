<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| روت‌های API ماژول‌ها به‌صورت خودکار توسط ServiceProvider هر ماژول لود
| می‌شوند (مثلاً Modules\Site\Routes\api.php).
| این فایل به‌عنوان نقطه‌ی شروع رسمی Laravel نگه داشته می‌شود؛ روت‌های
| سراسری (در صورت نیاز) می‌توانند مستقیماً اینجا تعریف شوند.
|
*/

// ─── Server-side Ads Call Tracking — عمومی، بدون لاگین (پشت CORS/throttle) ──
// ثبتِ attribution سبک است؛ throttle فقط جلوی abuse را می‌گیرد نه ترافیکِ
// واقعیِ کمپین را.
Route::post('/api/ads/attribution', [\App\Http\Controllers\Api\AdsTrackingController::class, 'attribution'])
    ->middleware('throttle:120,1')->name('api.ads.attribution');
Route::post('/api/ads/call-click', [\App\Http\Controllers\Api\AdsTrackingController::class, 'callClick'])
    ->middleware('throttle:60,1')->name('api.ads.call-click');
