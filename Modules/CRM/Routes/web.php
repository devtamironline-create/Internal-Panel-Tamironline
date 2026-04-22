<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Http\Controllers\BrandController;
use Modules\CRM\Http\Controllers\CrmController;

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
});
