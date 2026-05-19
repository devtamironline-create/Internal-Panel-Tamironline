<?php

use Illuminate\Support\Facades\Route;
use Modules\Site\Http\Controllers\Api\V1\ContactMessageController;

Route::prefix('v1')->group(function () {

    // ── Internal-only writes (BFF → API) ──────────────────────────
    Route::middleware(['internal.token', 'throttle:10,1'])->group(function () {
        Route::post('/contact-messages', [ContactMessageController::class, 'store'])
            ->name('api.v1.contact-messages.store');
    });

});
