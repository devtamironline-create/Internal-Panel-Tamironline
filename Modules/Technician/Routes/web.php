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
Route::post('/join-technician/register/biometric', [RegistrationController::class, 'submitBiometric'])->name('technician.register.biometric');
Route::post('/join-technician/register/biometric-status', [RegistrationController::class, 'checkBiometricStatus'])->name('technician.register.biometric-status');
Route::post('/join-technician/register/biometric-callback', [RegistrationController::class, 'biometricCallback'])->name('technician.register.biometric-callback');
Route::post('/join-technician/register/step2', [RegistrationController::class, 'storeStep2'])->name('technician.register.step2');
Route::post('/join-technician/register/step3', [RegistrationController::class, 'storeStep3'])->name('technician.register.step3');
Route::post('/join-technician/register/step4', [RegistrationController::class, 'storeStep4'])->name('technician.register.step4');
Route::post('/join-technician/register/step5', [RegistrationController::class, 'storeStep5'])->name('technician.register.step5');
Route::post('/join-technician/register/get-contract', [RegistrationController::class, 'getContract'])->name('technician.register.get-contract');
Route::post('/join-technician/register/send-contract-otp', [RegistrationController::class, 'sendContractOtp'])->name('technician.register.send-contract-otp');
Route::post('/join-technician/register/sign-contract', [RegistrationController::class, 'signContract'])->name('technician.register.sign-contract');
Route::post('/join-technician/register/upload-single-document', [RegistrationController::class, 'uploadSingleDocument'])->name('technician.register.upload-single-document');
Route::post('/join-technician/register/upload-documents', [RegistrationController::class, 'uploadDocuments'])->name('technician.register.upload-documents');

// بخش مدیریت تکنسین‌ها (نیاز به لاگین)
Route::middleware(['auth'])->prefix('admin/technician')->name('technician.admin.')->group(function () {
    Route::get('/settings', [TechnicianAdminController::class, 'settings'])->name('settings');
    Route::put('/settings', [TechnicianAdminController::class, 'updateSettings'])->name('settings.update');
    Route::post('/settings/reset', [TechnicianAdminController::class, 'resetDefaults'])->name('settings.reset');
    Route::get('/settings/delete-logo', [TechnicianAdminController::class, 'deleteLogo'])->name('settings.delete-logo');
    Route::get('/settings/delete-hero-bg', [TechnicianAdminController::class, 'deleteHeroBg'])->name('settings.delete-hero-bg');

    // لیست درخواست‌ها
    Route::get('/registrations', [TechnicianAdminController::class, 'registrations'])->name('registrations');
    Route::get('/registrations/{id}', [TechnicianAdminController::class, 'registrationShow'])->name('registrations.show');
    Route::put('/registrations/{id}/status', [TechnicianAdminController::class, 'registrationUpdateStatus'])->name('registrations.update-status');
    Route::put('/registrations/{id}/step', [TechnicianAdminController::class, 'registrationUpdateStep'])->name('registrations.update-step');
    Route::put('/registrations/{id}/note', [TechnicianAdminController::class, 'registrationUpdateNote'])->name('registrations.update-note');
    Route::put('/registrations/{id}/contract-fields', [TechnicianAdminController::class, 'registrationUpdateContractFields'])->name('registrations.update-contract-fields');
    Route::put('/registrations/{id}/biometric-review', [TechnicianAdminController::class, 'registrationBiometricReview'])->name('registrations.biometric-review');
    Route::delete('/registrations/{id}', [TechnicianAdminController::class, 'registrationDestroy'])->name('registrations.destroy');

    // مدیریت دسته‌بندی دستگاه‌ها
    Route::get('/appliance-categories', [TechnicianAdminController::class, 'applianceCategories'])->name('appliance-categories');
    Route::post('/appliance-categories', [TechnicianAdminController::class, 'storeApplianceCategory'])->name('appliance-categories.store');
    Route::put('/appliance-categories/{id}', [TechnicianAdminController::class, 'updateApplianceCategory'])->name('appliance-categories.update');
    Route::delete('/appliance-categories/{id}', [TechnicianAdminController::class, 'deleteApplianceCategory'])->name('appliance-categories.delete');
});
