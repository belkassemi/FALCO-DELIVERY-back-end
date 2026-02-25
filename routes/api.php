<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminPromotionController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourierController;
use App\Http\Controllers\Api\CourierActivationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\RestaurantDashboardController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SocketController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

// ============================================================
// PUBLIC AUTH ROUTES
// ============================================================
Route::group(['prefix' => 'auth'], function () {
    Route::post('register',             [AuthController::class, 'register']);
    Route::post('register-restaurant',  [AuthController::class, 'registerRestaurant']);
    Route::post('login',                [AuthController::class, 'login']);
    Route::post('refresh',              [AuthController::class, 'refresh']);
    Route::post('forgot-password',      [AuthController::class, 'forgotPassword']);
    Route::post('reset-password',       [AuthController::class, 'resetPassword']);
    Route::post('verify-email',         [AuthController::class, 'verifyEmail']);
});

// PAYMENT WEBHOOK — must be public (no JWT) so the gateway can call it
Route::post('payment/webhook', [PaymentController::class, 'webhook']);

// ============================================================
// PROTECTED ROUTES (requires JWT)
// ============================================================
Route::group(['middleware' => 'auth:api'], function () {

    // --- Auth / Profile ---
    Route::post('auth/logout',                  [AuthController::class, 'logout']);
    Route::post('auth/resend-verification',     [AuthController::class, 'resendVerification']);
    Route::post('auth/change-password',         [AuthController::class, 'changePassword']);

    Route::get('profile',                       [AuthController::class, 'profile']);
    Route::put('profile',                       [ProfileController::class, 'update']);
    Route::post('profile/upload-avatar',        [ProfileController::class, 'uploadAvatar']);
    Route::get('profile/addresses',             [ProfileController::class, 'getAddresses']);
    Route::post('profile/addresses',            [ProfileController::class, 'addAddress']);
    Route::delete('profile/addresses/{id}',     [ProfileController::class, 'deleteAddress']);

    // Profile Favorites (explicit POST/DELETE instead of toggle)
    Route::get('profile/favorites',                     [ProfileController::class, 'getFavorites']);
    Route::post('profile/favorites/{restaurantId}',     [ProfileController::class, 'addFavorite']);
    Route::delete('profile/favorites/{restaurantId}',   [ProfileController::class, 'removeFavorite']);

    // --- Public Restaurants (any authenticated user) ---
    Route::get('restaurants',                   [RestaurantController::class, 'index']);
    Route::get('restaurants/nearby',            [RestaurantController::class, 'nearby']);
    Route::get('restaurants/{id}',              [RestaurantController::class, 'show']);
    Route::get('restaurants/{id}/reviews',      [RestaurantController::class, 'reviews']);

    // --- Reviews (customer own CRUD) ---
    Route::post('restaurants/{id}/reviews',     [ReviewController::class, 'store']);
    Route::put('reviews/{id}',                  [ReviewController::class, 'update']);
    Route::delete('reviews/{id}',               [ReviewController::class, 'destroy']);

    // --- Socket Token ---
    Route::post('socket/token', [SocketController::class, 'generateToken']);

    // ============================================================
    // CUSTOMER ROUTES
    // ============================================================
    Route::group(['middleware' => RoleMiddleware::class . ':customer'], function () {
        Route::get('orders',                            [OrderController::class, 'history']);
        Route::post('orders',                           [OrderController::class, 'store']);
        Route::post('orders/pharmacy',                  [OrderController::class, 'store']);
        Route::get('orders/{id}',                       [OrderController::class, 'show']);
        Route::patch('orders/{id}/cancel',              [OrderController::class, 'cancel']);
        Route::get('orders/{id}/tracking',              [OrderController::class, 'track']);
        Route::post('orders/{id}/confirm-delivery',     [OrderController::class, 'confirmDelivery']);
        Route::post('orders/{id}/report-issue',         [OrderController::class, 'reportIssue']);
    });

    // ============================================================
    // COURIER ROUTES
    // ============================================================
    Route::group(['prefix' => 'courier', 'middleware' => RoleMiddleware::class . ':courier'], function () {
        Route::post('activate', [CourierActivationController::class, 'activate']);

        Route::group(['middleware' => 'courier.activated'], function () {
            Route::put('status',                        [CourierController::class, 'updateStatus']);
            Route::put('location',                      [CourierController::class, 'updateLocation'])->middleware('throttle:12,1');
            Route::get('orders/available',              [CourierController::class, 'availableOrders']);
            Route::get('orders/history',                [CourierController::class, 'history']);
            Route::post('orders/{id}/accept',           [CourierController::class, 'acceptOrder']);
            Route::post('orders/{id}/reject',           [CourierController::class, 'rejectOrder']);
            Route::post('orders/{id}/pickup',           [CourierController::class, 'pickupOrder']);
            Route::post('orders/{id}/deliver',          [CourierController::class, 'deliverOrder']);
            Route::get('earnings',                      [CourierController::class, 'earnings']);
            Route::post('withdraw-request',             [CourierController::class, 'withdrawRequest']);
            Route::get('withdraw-history',              [CourierController::class, 'withdrawHistory']);
        });
    });

    // ============================================================
    // RESTAURANT DASHBOARD (owner)
    // ============================================================
    Route::group(['prefix' => 'restaurant', 'middleware' => RoleMiddleware::class . ':restaurant_owner'], function () {
        Route::get('dashboard',                         [RestaurantDashboardController::class, 'index']);
        Route::put('profile',                           [RestaurantDashboardController::class, 'updateProfile']);
        Route::post('upload-image',                     [RestaurantDashboardController::class, 'uploadImage']);
        Route::put('status',                            [RestaurantDashboardController::class, 'updateStatus']);
        Route::get('menu',                              [RestaurantDashboardController::class, 'getMenu']);
        Route::post('menu',                             [RestaurantDashboardController::class, 'addMenuItem']);
        Route::put('menu/{id}',                         [RestaurantDashboardController::class, 'updateMenuItem']);
        Route::delete('menu/{id}',                      [RestaurantDashboardController::class, 'deleteMenuItem']);
        Route::get('orders',                            [RestaurantDashboardController::class, 'getOrders']);
        Route::get('orders/history',                    [RestaurantDashboardController::class, 'orderHistory']);
        Route::put('orders/{id}/accept',                [RestaurantDashboardController::class, 'acceptOrder']);
        Route::put('orders/{id}/ready',                 [RestaurantDashboardController::class, 'orderReady']);
        Route::put('orders/{id}/reject',                [RestaurantDashboardController::class, 'rejectOrder']);
        Route::get('analytics',                         [RestaurantDashboardController::class, 'analytics']);
    });

    // ============================================================
    // ADMIN PANEL
    // ============================================================
    Route::group(['prefix' => 'admin', 'middleware' => RoleMiddleware::class . ':admin'], function () {

        // Users
        Route::get('users',                             [AdminController::class, 'users']);
        Route::get('users/{id}',                        [AdminController::class, 'showUser']);
        Route::patch('users/{id}/status',               [AdminController::class, 'updateStatus']);

        // Couriers
        Route::post('couriers',                         [AdminController::class, 'createCourier']);
        Route::get('couriers',                          [AdminController::class, 'couriers']);
        Route::patch('couriers/{id}/status',            [AdminController::class, 'updateCourierStatus']);

        // Restaurants
        Route::get('restaurants',                       [AdminController::class, 'restaurants']);
        Route::get('restaurants/pending',               [AdminController::class, 'pendingRestaurants']);
        Route::post('restaurants/{id}/approve',         [AdminController::class, 'approveRestaurant']);
        Route::patch('restaurants/{id}/status',         [AdminController::class, 'updateRestaurantStatus']);

        // Orders
        Route::get('orders',                            [AdminController::class, 'orders']);
        Route::get('orders/{id}',                       [AdminController::class, 'showOrder']);
        Route::patch('orders/{id}/force-status',        [AdminController::class, 'forceOrderStatus']);

        // Analytics
        Route::get('analytics',                         [AdminController::class, 'analytics']);

        // Menu Change Approvals
        Route::get('menu-changes',                      [AdminController::class, 'pendingMenuChanges']);
        Route::post('menu-changes/{id}/approve',        [AdminController::class, 'approveMenuChange']);
        Route::post('menu-changes/{id}/reject',         [AdminController::class, 'rejectMenuChange']);

        // Promotions CRUD
        Route::post('promotions',                       [AdminPromotionController::class, 'store']);
        Route::put('promotions/{id}',                   [AdminPromotionController::class, 'update']);
        Route::delete('promotions/{id}',                [AdminPromotionController::class, 'destroy']);

        // Reviews Moderation
        Route::delete('reviews/{id}',                   [ReviewController::class, 'adminDestroy']);

        // Refunds
        Route::get('refunds',                           [AdminController::class, 'refunds']);
        Route::post('refunds/{id}/approve',             [AdminController::class, 'approveRefund']);
        Route::post('refunds/{id}/reject',              [AdminController::class, 'rejectRefund']);

        // Notifications Broadcast
        Route::post('notifications/broadcast',          [AdminController::class, 'broadcastNotification']);

        // Audit & Monitoring
        Route::get('logs',                              [AuditController::class, 'logs']);
        Route::get('login-attempts',                    [AuditController::class, 'loginAttempts']);
        Route::get('system-health',                     [AuditController::class, 'systemHealth']);
    });

    // ============================================================
    // WALLET
    // ============================================================
    Route::get('wallet',                [WalletController::class, 'show']);
    Route::post('wallet/top-up',        [WalletController::class, 'topUp']);
    Route::get('wallet/history',        [WalletController::class, 'history']);

    // ============================================================
    // PAYMENTS
    // ============================================================
    Route::post('payment/checkout',         [PaymentController::class, 'checkout']);
    Route::get('payment/history',           [PaymentController::class, 'history']);
    Route::post('payment/refund-request',   [PaymentController::class, 'refundRequest']);
    Route::get('payment/refund-status/{id}',[PaymentController::class, 'refundStatus']);

    // ============================================================
    // PROMOTIONS
    // ============================================================
    Route::get('promotions',            [PromotionController::class, 'index']);
    Route::post('promotions/apply',     [PromotionController::class, 'apply']);

    // ============================================================
    // NOTIFICATIONS
    // ============================================================
    Route::get('notifications',                     [NotificationController::class, 'index']);
    Route::put('notifications/{id}/read',           [NotificationController::class, 'markRead']);
    Route::post('notifications/device-token',       [NotificationController::class, 'registerDeviceToken']);
    Route::post('notifications/mark-all-read',      [NotificationController::class, 'markAllRead']);

});
