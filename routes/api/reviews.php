<?php

use App\Http\Controllers\Reviews\V1\StoreProductController;
use App\Http\Controllers\Reviews\V1\StoreStoreController;
use Illuminate\Support\Facades\Route;

/**
 * Customer Review Routes — Requires Authentication
 * Protected by auth:sanctum
 */
Route::middleware(['auth:sanctum'])->group(function () {
    // Submit product reviews after delivery
    Route::post('/reviews/products', StoreProductController::class);
    
    // Submit or update store review after at least one delivery
    Route::post('/reviews/stores', StoreStoreController::class);
});
