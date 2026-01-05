<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Controllers\AttendanceController;

/*
|--------------------------------------------------------------------------
| Attendance Module Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->prefix('attendance')->group(function () {
    // Employee routes
    Route::get('/', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');
    Route::get('/history', [AttendanceController::class, 'history'])->name('attendance.history');

    // Admin routes
    Route::middleware(['can:manage-attendance'])->group(function () {
        Route::get('/admin', [AttendanceController::class, 'adminIndex'])->name('attendance.admin');
        Route::get('/settings', [AttendanceController::class, 'settings'])->name('attendance.settings');
        Route::put('/settings', [AttendanceController::class, 'updateSettings'])->name('attendance.settings.update');
    });
});
