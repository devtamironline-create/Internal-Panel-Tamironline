<?php

use Illuminate\Support\Facades\Route;
use Modules\Warehouse\Http\Controllers\WebhookController;

/*
 *--------------------------------------------------------------------------
 * API Routes
 *--------------------------------------------------------------------------
 *
 * Here is where you can register API routes for your application. These
 * routes are loaded by the RouteServiceProvider within a group which
 * is assigned the "api" middleware group.
 *
*/

// Webhook routes (no auth required - uses webhook secret)
Route::prefix('warehouse')->name('api.warehouse.')->group(function () {
    Route::get('/ping', [WebhookController::class, 'ping'])->name('ping');
    Route::post('/webhook/order', [WebhookController::class, 'handleOrder'])->name('webhook.order');
    Route::post('/webhook/status', [WebhookController::class, 'handleStatusUpdate'])->name('webhook.status');
});
