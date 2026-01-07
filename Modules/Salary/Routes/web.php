<?php

use Illuminate\Support\Facades\Route;
use Modules\Salary\Http\Controllers\SalaryController;
use Modules\Salary\Http\Controllers\SalaryAdminController;
use Modules\Salary\Http\Controllers\SalarySettingController;

Route::middleware(['web', 'auth'])->prefix('admin/salary')->name('salary.')->group(function () {

    // Employee routes - view own salary
    Route::get('/', [SalaryController::class, 'dashboard'])->name('dashboard');
    Route::get('/history', [SalaryController::class, 'history'])->name('history');
    Route::get('/payslip/{salary}', [SalaryController::class, 'show'])->name('show');
    Route::get('/payslip/{salary}/pdf', [SalaryController::class, 'pdf'])->name('pdf');

    // Admin routes - manage all salaries
    Route::middleware('can:manage-salary')->prefix('manage')->name('admin.')->group(function () {
        Route::get('/', [SalaryAdminController::class, 'index'])->name('index');
        Route::get('/{salary}', [SalaryAdminController::class, 'show'])->name('show');
        Route::get('/{salary}/edit', [SalaryAdminController::class, 'edit'])->name('edit');
        Route::put('/{salary}', [SalaryAdminController::class, 'update'])->name('update');
        Route::get('/{salary}/pdf', [SalaryAdminController::class, 'pdf'])->name('pdf');

        Route::post('/calculate-all', [SalaryAdminController::class, 'calculateAll'])->name('calculate-all');
        Route::post('/calculate/{user}', [SalaryAdminController::class, 'calculate'])->name('calculate');
        Route::post('/{salary}/approve', [SalaryAdminController::class, 'approve'])->name('approve');
        Route::post('/approve-all', [SalaryAdminController::class, 'approveAll'])->name('approve-all');
        Route::post('/{salary}/paid', [SalaryAdminController::class, 'markPaid'])->name('paid');
        Route::get('/export/csv', [SalaryAdminController::class, 'export'])->name('export');
    });

    // Settings routes
    Route::middleware('can:manage-salary')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SalarySettingController::class, 'index'])->name('index');
        Route::put('/', [SalarySettingController::class, 'update'])->name('update');
        Route::get('/employees', [SalarySettingController::class, 'employees'])->name('employees');
        Route::get('/employees/{user}', [SalarySettingController::class, 'editEmployee'])->name('edit-employee');
        Route::put('/employees/{user}', [SalarySettingController::class, 'updateEmployee'])->name('update-employee');
    });
});
