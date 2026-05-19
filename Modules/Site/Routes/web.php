<?php

use Illuminate\Support\Facades\Route;
use Modules\Site\Http\Controllers\SiteAdminController;

// بخش مدیریت سایت (نیاز به لاگین)
Route::middleware(['auth'])->prefix('admin/site')->name('site.admin.')->group(function () {
    Route::get('/', [SiteAdminController::class, 'dashboard'])->name('dashboard');
});
