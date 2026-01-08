<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Controllers\AttendanceController;
use Modules\Attendance\Http\Controllers\LeaveController;

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
    Route::post('/lunch-start', [AttendanceController::class, 'startLunch'])->name('attendance.lunch-start');
    Route::post('/lunch-end', [AttendanceController::class, 'endLunch'])->name('attendance.lunch-end');
    Route::get('/history', [AttendanceController::class, 'history'])->name('attendance.history');

    // Admin routes
    Route::middleware(['can:manage-attendance'])->group(function () {
        Route::get('/admin', [AttendanceController::class, 'adminIndex'])->name('attendance.admin');
        Route::get('/settings', [AttendanceController::class, 'settings'])->name('attendance.settings');
        Route::put('/settings', [AttendanceController::class, 'updateSettings'])->name('attendance.settings.update');
    });
});

/*
|--------------------------------------------------------------------------
| Leave Management Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->prefix('leave')->group(function () {
    // Employee leave routes
    Route::get('/', [LeaveController::class, 'index'])->name('leave.index');
    Route::get('/create', [LeaveController::class, 'create'])->name('leave.create');
    Route::post('/', [LeaveController::class, 'store'])->name('leave.store');
    Route::get('/{leaveRequest}', [LeaveController::class, 'show'])->name('leave.show');
    Route::delete('/{leaveRequest}', [LeaveController::class, 'cancel'])->name('leave.cancel');

    // Approval routes (for supervisors and managers)
    Route::get('/manage/approvals', [LeaveController::class, 'approvals'])->name('leave.approvals');
    Route::post('/{leaveRequest}/approve', [LeaveController::class, 'approve'])->name('leave.approve');
    Route::post('/{leaveRequest}/reject', [LeaveController::class, 'reject'])->name('leave.reject');

    // Admin routes
    Route::middleware(['can:manage-attendance'])->group(function () {
        Route::get('/manage/all', [LeaveController::class, 'adminIndex'])->name('leave.admin');
    });
});
