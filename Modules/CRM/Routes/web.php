<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Http\Controllers\CrmController;

Route::middleware(['auth'])->prefix('admin/crm')->name('crm.')->group(function () {
    Route::get('/', [CrmController::class, 'dashboard'])
        ->middleware('can:view-crm-dashboard')
        ->name('dashboard');
});
