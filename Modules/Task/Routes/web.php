<?php

use Illuminate\Support\Facades\Route;
use Modules\Task\Http\Controllers\TaskController;
use Modules\Task\Http\Controllers\TeamController;

/*
|--------------------------------------------------------------------------
| Task Module Routes
|--------------------------------------------------------------------------
*/

// Team Management Routes
Route::middleware(['web', 'auth'])->prefix('teams')->group(function () {
    Route::get('/', [TeamController::class, 'index'])->name('teams.index');
    Route::get('/create', [TeamController::class, 'create'])->name('teams.create');
    Route::post('/', [TeamController::class, 'store'])->name('teams.store');
    Route::get('/{team}/edit', [TeamController::class, 'edit'])->name('teams.edit');
    Route::put('/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
    Route::post('/order', [TeamController::class, 'updateOrder'])->name('teams.order');
    Route::patch('/{team}/toggle', [TeamController::class, 'toggleStatus'])->name('teams.toggle');
});

// Task Management Routes
Route::middleware(['web', 'auth'])->prefix('tasks')->group(function () {
    // Main views
    Route::get('/', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/my', [TaskController::class, 'myTasks'])->name('tasks.my');

    // CRUD
    Route::get('/create', [TaskController::class, 'create'])->name('tasks.create');
    Route::post('/', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::get('/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::put('/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    // AJAX endpoints
    Route::patch('/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
    Route::post('/order', [TaskController::class, 'updateOrder'])->name('tasks.order');

    // Comments
    Route::post('/{task}/comment', [TaskController::class, 'addComment'])->name('tasks.comment');

    // Checklist
    Route::post('/{task}/checklist', [TaskController::class, 'addChecklist'])->name('tasks.checklist.add');
    Route::post('/checklist/{checklist}/toggle', [TaskController::class, 'toggleChecklist'])->name('tasks.checklist.toggle');

    // Reports
    Route::get('/reports/users', [TaskController::class, 'userReport'])->name('tasks.reports.users');
});
