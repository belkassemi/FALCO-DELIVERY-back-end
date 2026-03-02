<?php

use App\Http\Controllers\Reports\V1\StoreController;
use App\Http\Controllers\Reports\V1\IndexController;
use App\Http\Controllers\Admin\Reports\V1\IndexController as AdminIndexController;
use App\Http\Controllers\Admin\Reports\V1\ResolveController as AdminResolveController;
use Illuminate\Support\Facades\Route;

/**
 * Order Issue Reporting Routes
 * Protected by auth:sanctum
 */

// --- Customer Routes ---
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/reports', StoreController::class);
    Route::get('/reports', IndexController::class);
});

// --- Admin Routes ---
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/reports', AdminIndexController::class);
    Route::put('/reports/{id}/resolve', AdminResolveController::class);
});
