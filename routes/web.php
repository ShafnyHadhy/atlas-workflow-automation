<?php

use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkflowController;
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

Route::middleware(['auth', 'verified', 'workspace.access'])->group(function () {
    Route::get('/workspaces/{workspace}', [WorkspaceController::class, 'show'])
        ->name('workspaces.show');

    Route::post('/workspaces/{workspace}/workflows', [WorkflowController::class, 'store'])
        ->name('workspaces.workflows.store');

    Route::get('/workspaces/{workspace}/workflows/{workflow:slug}', [WorkflowController::class, 'show'])
        ->scopeBindings()
        ->name('workspaces.workflows.show');
});

require __DIR__.'/settings.php';
