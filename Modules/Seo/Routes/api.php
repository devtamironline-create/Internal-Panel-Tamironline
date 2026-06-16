<?php

use Illuminate\Support\Facades\Route;
use Modules\Seo\Http\Controllers\Api\MetaController;
use Modules\Seo\Http\Controllers\Api\SettingsController;

/*
 * API هدلس سئو — هم‌راستا با کنوانسیون پروژه: prefix «v1» و بدون «/api».
 * (پاسخ‌های v1/* به‌صورت JSON و با هندلینگ خطای اختصاصی برمی‌گردند.)
 */
Route::prefix('v1/seo')->middleware('throttle:120,1')->group(function () {
    Route::get('/meta', [MetaController::class, 'show'])->name('api.v1.seo.meta');
    Route::get('/settings', [SettingsController::class, 'show'])->name('api.v1.seo.settings');
});
