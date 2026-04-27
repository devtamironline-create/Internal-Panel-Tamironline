<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CRM Sync API
|--------------------------------------------------------------------------
|
| این فایل توسط RouteServiceProvider در گروه middleware('verify-wp-sync')
| لود می‌شود و prefix /api/crm/sync دارد.
|
| همه endpointها فقط برای مصرف افزونهٔ Tamironline CRM Sync روی وردپرس
| طراحی شده‌اند. احراز هویت با Bearer token از crm_settings.wp_sync_token.
|
*/

Route::get('/ping', function () {
    return response()->json([
        'ok' => true,
        'message' => 'pong',
        'time' => now()->toIso8601String(),
    ]);
})->name('crm.sync.ping');
