<?php

use Illuminate\Support\Facades\Route;
use Modules\Warehouse\Http\Controllers\WarehouseController;

/*
|--------------------------------------------------------------------------
| Warehouse Module Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->prefix('warehouse')->group(function () {
    Route::get('/', [WarehouseController::class, 'index'])->name('warehouse.index');
    Route::get('/create', [WarehouseController::class, 'create'])->name('warehouse.create');
    Route::post('/', [WarehouseController::class, 'store'])->name('warehouse.store');
    Route::get('/{order}', [WarehouseController::class, 'show'])->name('warehouse.show');
    Route::get('/{order}/edit', [WarehouseController::class, 'edit'])->name('warehouse.edit');
    Route::put('/{order}', [WarehouseController::class, 'update'])->name('warehouse.update');
    Route::patch('/{order}/status', [WarehouseController::class, 'updateStatus'])->name('warehouse.status');
    Route::delete('/{order}', [WarehouseController::class, 'destroy'])->name('warehouse.destroy');
});
