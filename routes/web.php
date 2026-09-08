<?php

use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('/workspaces', [WorkspaceController::class, 'index'])
        ->name('workspaces.index');

    Route::post('/workspaces', [WorkspaceController::class, 'store'])
        ->name('workspaces.store');

    Route::post('/workspaces/{workspace}/select', [WorkspaceController::class, 'select'])
    ->name('workspaces.select');
});

Route::middleware(['auth', 'verified', 'workspace.access'])
    ->get('/workspaces/{workspace}', [WorkspaceController::class, 'show'])
    ->name('workspaces.show');

require __DIR__.'/settings.php';
