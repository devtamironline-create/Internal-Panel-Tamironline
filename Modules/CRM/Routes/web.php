<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Http\Controllers\BrandController;
use Modules\CRM\Http\Controllers\CrmController;
use Modules\CRM\Http\Controllers\DeviceController;
use Modules\CRM\Http\Controllers\ProvinceController;

Route::middleware(['auth'])->prefix('admin/crm')->name('crm.')->group(function () {
    Route::get('/', [CrmController::class, 'dashboard'])
        ->middleware('can:view-crm-dashboard')
        ->name('dashboard');

    // ─── تاکسونومی ── برندها ───────────────────────────────────────
    Route::middleware('can:view-crm-taxonomies')->group(function () {
        Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
    });
    Route::middleware('can:manage-crm-brands')->group(function () {
        Route::get('brands/create', [BrandController::class, 'create'])->name('brands.create');
        Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
        Route::get('brands/{brand}/edit', [BrandController::class, 'edit'])->name('brands.edit');
        Route::put('brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
        Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');
    });

    // ─── تاکسونومی ── دستگاه‌ها ─────────────────────────────────────
    Route::middleware('can:view-crm-taxonomies')->group(function () {
        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    });
    Route::middleware('can:manage-crm-devices')->group(function () {
        Route::get('devices/create', [DeviceController::class, 'create'])->name('devices.create');
        Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::get('devices/{device}/edit', [DeviceController::class, 'edit'])->name('devices.edit');
        Route::put('devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
        Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
    });

    // ─── تاکسونومی ── استان‌ها ──────────────────────────────────────
    Route::middleware('can:view-crm-taxonomies')->group(function () {
        Route::get('provinces', [ProvinceController::class, 'index'])->name('provinces.index');
    });
    Route::middleware('can:manage-crm-provinces')->group(function () {
        Route::get('provinces/create', [ProvinceController::class, 'create'])->name('provinces.create');
        Route::post('provinces', [ProvinceController::class, 'store'])->name('provinces.store');
        Route::get('provinces/{province}/edit', [ProvinceController::class, 'edit'])->name('provinces.edit');
        Route::put('provinces/{province}', [ProvinceController::class, 'update'])->name('provinces.update');
        Route::delete('provinces/{province}', [ProvinceController::class, 'destroy'])->name('provinces.destroy');
    });
});
