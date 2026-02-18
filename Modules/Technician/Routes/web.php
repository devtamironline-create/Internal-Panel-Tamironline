<?php

use Illuminate\Support\Facades\Route;
use Modules\Technician\Http\Controllers\LandingController;
use Modules\Technician\Http\Controllers\TechnicianAdminController;

// صفحه عمومی جذب تکنسین (بدون نیاز به لاگین)
Route::get('/join-technician', [LandingController::class, 'show'])->name('technician.landing');

// بخش مدیریت تکنسین‌ها (نیاز به لاگین)
Route::middleware(['auth'])->prefix('admin/technician')->name('technician.admin.')->group(function () {
    Route::get('/settings', [TechnicianAdminController::class, 'settings'])->name('settings');
    Route::put('/settings', [TechnicianAdminController::class, 'updateSettings'])->name('settings.update');
    Route::post('/settings/reset', [TechnicianAdminController::class, 'resetDefaults'])->name('settings.reset');
});
