<?php

use Illuminate\Support\Facades\Route;
use Modules\Technician\Http\Controllers\LandingController;
use Modules\Technician\Http\Controllers\RegistrationController;
use Modules\Technician\Http\Controllers\TechnicianAdminController;

// صفحه عمومی جذب تکنسین (بدون نیاز به لاگین)
Route::get('/join-technician', [LandingController::class, 'show'])->name('technician.landing');

// فرآیند ثبت‌نام تکنسین (بدون نیاز به لاگین)
Route::get('/join-technician/register', [RegistrationController::class, 'showForm'])->name('technician.register');
Route::post('/join-technician/register/send-otp', [RegistrationController::class, 'sendOtp'])->name('technician.register.send-otp');
Route::post('/join-technician/register/verify-otp', [RegistrationController::class, 'verifyOtp'])->name('technician.register.verify-otp');
Route::post('/join-technician/register/step1', [RegistrationController::class, 'storeStep1'])->name('technician.register.step1');
Route::post('/join-technician/register/step2', [RegistrationController::class, 'storeStep2'])->name('technician.register.step2');

// بخش مدیریت تکنسین‌ها (نیاز به لاگین)
Route::middleware(['auth'])->prefix('admin/technician')->name('technician.admin.')->group(function () {
    Route::get('/settings', [TechnicianAdminController::class, 'settings'])->name('settings');
    Route::put('/settings', [TechnicianAdminController::class, 'updateSettings'])->name('settings.update');
    Route::post('/settings/reset', [TechnicianAdminController::class, 'resetDefaults'])->name('settings.reset');
    Route::get('/settings/delete-logo', [TechnicianAdminController::class, 'deleteLogo'])->name('settings.delete-logo');
    Route::get('/settings/delete-hero-bg', [TechnicianAdminController::class, 'deleteHeroBg'])->name('settings.delete-hero-bg');
});
