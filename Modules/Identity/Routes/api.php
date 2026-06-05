<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\Api\V1\AuthController;

Route::prefix('api/v1/auth')->group(function () {
    // Public — rate-limited
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/send-otp', [AuthController::class, 'sendOtp'])->name('identity.send-otp');
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('identity.verify-otp');
    });

    // Auth required
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('identity.me');
        Route::post('/complete-profile', [AuthController::class, 'completeProfile'])->name('identity.complete-profile');
        Route::post('/logout', [AuthController::class, 'logout'])->name('identity.logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('identity.logout-all');
    });
});
