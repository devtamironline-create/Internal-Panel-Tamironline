<?php

use Illuminate\Support\Facades\Route;
use Modules\OKR\Http\Controllers\OKRController;
use Modules\OKR\Http\Controllers\CycleController;
use Modules\OKR\Http\Controllers\ObjectiveController;
use Modules\OKR\Http\Controllers\KeyResultController;

Route::middleware(['auth', 'staff'])->prefix('admin/okr')->name('okr.')->group(function () {
    // Dashboard
    Route::get('/', [OKRController::class, 'dashboard'])->name('dashboard');

    // Cycles
    Route::resource('cycles', CycleController::class);
    Route::post('cycles/{cycle}/activate', [CycleController::class, 'activate'])->name('cycles.activate');
    Route::post('cycles/{cycle}/close', [CycleController::class, 'close'])->name('cycles.close');

    // Objectives
    Route::resource('objectives', ObjectiveController::class);
    Route::get('my-objectives', [ObjectiveController::class, 'myObjectives'])->name('objectives.my');

    // Key Results
    Route::resource('key-results', KeyResultController::class)->except(['index']);
    Route::post('key-results/{keyResult}/check-in', [KeyResultController::class, 'checkIn'])->name('key-results.check-in');
});
