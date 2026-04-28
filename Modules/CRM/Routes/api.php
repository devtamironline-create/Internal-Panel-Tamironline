<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Http\Controllers\Api\SyncCustomerController;
use Modules\CRM\Http\Controllers\Api\SyncFinancialController;
use Modules\CRM\Http\Controllers\Api\SyncOrderController;
use Modules\CRM\Http\Controllers\Api\SyncSettingController;
use Modules\CRM\Http\Controllers\Api\SyncTaxonomyController;
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

// ─── تاکسونومی‌ها (برند، دستگاه، استان، شهر) ─────────────────────
Route::post('/taxonomy/{type}', [SyncTaxonomyController::class, 'upsert'])
    ->where('type', 'brand|device|province|city')
    ->name('crm.sync.taxonomy');
Route::post('/taxonomy/{type}/batch', [SyncTaxonomyController::class, 'batch'])
    ->where('type', 'brand|device|province|city')
    ->name('crm.sync.taxonomy.batch');

// ─── سفارش‌ها ─────────────────────────────────────────────────────
Route::post('/order', [SyncOrderController::class, 'upsert'])->name('crm.sync.order');
Route::post('/orders/batch', [SyncOrderController::class, 'batch'])->name('crm.sync.orders.batch');

// ─── مالی (financial) — روتر: invoice / wallet_credit / reward / penalty ──
Route::post('/financial', [SyncFinancialController::class, 'upsert'])->name('crm.sync.financial');
Route::post('/financials/batch', [SyncFinancialController::class, 'batch'])->name('crm.sync.financials.batch');
