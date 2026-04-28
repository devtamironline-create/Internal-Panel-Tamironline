<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Http\Controllers\Api\SyncCustomerController;
use Modules\CRM\Http\Controllers\Api\SyncSettingController;
use Modules\CRM\Http\Controllers\Api\SyncTechnicianController;

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

// ─── مشتری‌ها ─────────────────────────────────────────────────────
Route::post('/customer', [SyncCustomerController::class, 'upsert'])->name('crm.sync.customer');
Route::post('/customers/batch', [SyncCustomerController::class, 'batch'])->name('crm.sync.customers.batch');

// ─── تکنسین‌ها ────────────────────────────────────────────────────
Route::post('/technician', [SyncTechnicianController::class, 'upsert'])->name('crm.sync.technician');
Route::post('/technicians/batch', [SyncTechnicianController::class, 'batch'])->name('crm.sync.technicians.batch');

// ─── تنظیمات ──────────────────────────────────────────────────────
Route::post('/setting', [SyncSettingController::class, 'upsert'])->name('crm.sync.setting');
Route::post('/settings/batch', [SyncSettingController::class, 'batch'])->name('crm.sync.settings.batch');
