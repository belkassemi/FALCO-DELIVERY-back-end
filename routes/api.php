<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourierController;
use App\Http\Controllers\Api\CourierActivationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\RestaurantDashboardController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

// --- Public Auth Routes ---
Route::group(['prefix' => 'auth'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
});

// --- Protected API Routes ---
Route::group(['middleware' => 'auth:api'], function () {

    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Profile
    Route::get('profile', [AuthController::class, 'profile']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::post('profile/upload-avatar', [ProfileController::class, 'uploadAvatar']);
    Route::get('profile/addresses', [ProfileController::class, 'getAddresses']);
    Route::post('profile/addresses', [ProfileController::class, 'addAddress']);
    Route::delete('profile/addresses/{id}', [ProfileController::class, 'deleteAddress']);
    Route::get('profile/favorites', [ProfileController::class, 'getFavorites']);
    Route::post('profile/favorites/{id}', [ProfileController::class, 'toggleFavorite']);

    // Restaurants (Customer view)
    Route::get('restaurants', [RestaurantController::class, 'index']);
    Route::get('restaurants/nearby', [RestaurantController::class, 'nearby']);
    Route::get('restaurants/{id}', [RestaurantController::class, 'show']);
    Route::get('restaurants/{id}/reviews', [RestaurantController::class, 'reviews']);

    // Orders (Customer)
    Route::group(['middleware' => RoleMiddleware::class . ':customer'], function () {
        Route::get('orders', [OrderController::class, 'history']);
        Route::post('orders/create', [OrderController::class, 'store']);
        Route::post('orders/pharmacy', [OrderController::class, 'store']); // Uses same logic with redirect
        Route::put('orders/{id}/cancel', [OrderController::class, 'cancel']);
        Route::get('orders/{id}/track', [OrderController::class, 'track']);
    });

    // Courier Operations
    Route::group(['prefix' => 'courier', 'middleware' => RoleMiddleware::class . ':courier'], function () {
        Route::post('activate', [CourierActivationController::class, 'activate']);

        Route::group(['middleware' => 'courier.activated'], function () {
            Route::put('status', [CourierController::class, 'updateStatus']);
            Route::put('location', [CourierController::class, 'updateLocation']);
            Route::get('orders/history', [CourierController::class, 'history']);
            Route::post('orders/{id}/accept', [CourierController::class, 'acceptOrder']);
            Route::post('orders/{id}/pickup', [CourierController::class, 'pickupOrder']);
            Route::post('orders/{id}/deliver', [CourierController::class, 'deliverOrder']);
            Route::get('earnings', [CourierController::class, 'earnings']);
        });
    });

    // Restaurant Dashboard (Owner)
    Route::group(['prefix' => 'restaurant', 'middleware' => RoleMiddleware::class . ':restaurant_owner'], function () {
        Route::get('dashboard', [RestaurantDashboardController::class, 'index']);
        Route::put('profile', [RestaurantDashboardController::class, 'updateProfile']);
        Route::put('status', [RestaurantDashboardController::class, 'updateStatus']);
        Route::get('orders', [RestaurantDashboardController::class, 'getOrders']);
        Route::put('orders/{id}/accept', [RestaurantDashboardController::class, 'acceptOrder']);
        Route::put('orders/{id}/ready', [RestaurantDashboardController::class, 'orderReady']);
        Route::post('menu', [RestaurantDashboardController::class, 'addMenuItem']);
        Route::put('menu/{id}', [RestaurantDashboardController::class, 'updateMenuItem']);
        Route::delete('menu/{id}', [RestaurantDashboardController::class, 'deleteMenuItem']);
        Route::get('analytics', [RestaurantDashboardController::class, 'analytics']);
    });

    // Admin Panel
    Route::group(['prefix' => 'admin', 'middleware' => RoleMiddleware::class . ':admin'], function () {
        Route::get('users', [AdminController::class, 'users']);
        Route::put('users/{id}/block', [AdminController::class, 'blockUser']);
        Route::post('couriers', [AdminController::class, 'createCourier']);
        Route::get('restaurants/pending', [AdminController::class, 'pendingRestaurants']);
        Route::post('restaurants/{id}/approve', [AdminController::class, 'approveRestaurant']);
        Route::get('orders', [AdminController::class, 'orders']);
        Route::get('analytics', [AdminController::class, 'analytics']);
        
        // Menu Change Approvals
        Route::get('menu-changes', [AdminController::class, 'pendingMenuChanges']);
        Route::post('menu-changes/{id}/approve', [AdminController::class, 'approveMenuChange']);
        Route::post('menu-changes/{id}/reject', [AdminController::class, 'rejectMenuChange']);
    });

    // Wallet & Promotions
    Route::get('wallet', [WalletController::class, 'show']);
    Route::post('wallet/top-up', [WalletController::class, 'topUp']);
    Route::get('wallet/history', [WalletController::class, 'history']);

    Route::get('promotions', [PromotionController::class, 'index']);
    Route::post('promotions/apply', [PromotionController::class, 'apply']);

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::put('notifications/{id}/read', [NotificationController::class, 'markRead']);

});
