<?php

use Illuminate\Support\Facades\Route;
use Modules\CustomerApp\Http\Controllers\Api\V1\StatusController;
use Modules\CustomerApp\Http\Middleware\ApiEnvelope;
use Modules\CustomerApp\Http\Middleware\IdempotencyKey;
use Modules\CustomerApp\Http\Middleware\RollingToken;

/*
|--------------------------------------------------------------------------
| Customer App API — /v1/customer/*
|--------------------------------------------------------------------------
| روت‌های اپلیکیشن موبایل/PWA مشتری‌ها. envelope استاندارد، Idempotency-Key،
| RollingToken و auth:sanctum روی همه‌ی روت‌های private اعمال می‌شوند.
| Auth subject: Modules\CRM\Models\Customer (همان توکن Identity).
|
| قراردادها در docs/MOBILE_API_CONTRACT.md
*/

Route::prefix('v1/customer')
    ->middleware([
        ApiEnvelope::class,
        IdempotencyKey::class,
    ])
    ->group(function () {

        // ─── Public — هیچ auth لازم نیست ─────────────────────────
        Route::get('/status', StatusController::class)->name('api.customer.status');

        // ─── Private — auth:sanctum + rolling token ──────────────
        Route::middleware(['auth:sanctum', RollingToken::class])->group(function () {
            // در turn های بعدی Block 1..N (orders, addresses, reviews,
            // invoices, notifications, profile, ...) اینجا اضافه می‌شود
        });
    });
