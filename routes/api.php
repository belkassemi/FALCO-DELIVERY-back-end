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
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\StoreDashboardController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SocketController;

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

// ============================================================
// PUBLIC AUTH ROUTES
// ============================================================
Route::group(['prefix' => 'auth'], function () {
    // Phone-first OTP auth (customers — PRD lazy signup)
    Route::post('request-otp',          [AuthController::class, 'requestOtp']);
    Route::post('verify-otp',           [AuthController::class, 'verifyOtp']);

    // Traditional auth (store owners, admins)
    Route::post('register-store',       [AuthController::class, 'registerStore']);
    Route::post('login',                [AuthController::class, 'login']);
    Route::post('refresh',              [AuthController::class, 'refresh']);
});

// PAYMENT WEBHOOK — public (no JWT)
Route::post('payment/webhook', [PaymentController::class, 'webhook']);

// PUBLIC: List categories (for anonymous browsing)
Route::get('categories', [StoreController::class, 'categories']);

// ============================================================
// PROTECTED ROUTES (requires JWT)
// ============================================================
Route::group(['middleware' => 'auth:api'], function () {

    // --- Auth / Profile ---
    Route::post('auth/logout',              [AuthController::class, 'logout']);
    Route::post('auth/change-password',     [AuthController::class, 'changePassword']);

    Route::get('profile',                   [AuthController::class, 'profile']);
    Route::put('profile',                   [ProfileController::class, 'update']);
    Route::post('profile/upload-avatar',    [ProfileController::class, 'uploadAvatar']);
    Route::get('profile/addresses',         [ProfileController::class, 'getAddresses']);
    Route::post('profile/addresses',        [ProfileController::class, 'addAddress']);
    Route::delete('profile/addresses/{id}', [ProfileController::class, 'deleteAddress']);

    // Profile Favorites
    Route::get('profile/favorites',                     [ProfileController::class, 'getFavorites']);
    Route::post('profile/favorites/{storeId}',          [ProfileController::class, 'addFavorite']);
    Route::delete('profile/favorites/{storeId}',        [ProfileController::class, 'removeFavorite']);

    // --- Public Stores (any authenticated user) ---
    Route::get('stores',                    [StoreController::class, 'index']);
    Route::get('stores/nearby',             [StoreController::class, 'nearby']);
    Route::get('stores/{id}',               [StoreController::class, 'show']);
    Route::get('stores/{id}/reviews',       [StoreController::class, 'reviews']);

    // --- Reviews ---
    Route::post('stores/{id}/reviews',      [ReviewController::class, 'store']);
    Route::put('reviews/{id}',              [ReviewController::class, 'update']);
    Route::delete('reviews/{id}',           [ReviewController::class, 'destroy']);

    // --- Socket Token ---
    Route::post('socket/token', [SocketController::class, 'generateToken']);

    // ============================================================
    // CUSTOMER ROUTES
    // ============================================================
    Route::group(['middleware' => RoleMiddleware::class . ':customer'], function () {
        Route::get('orders',                            [OrderController::class, 'history']);
        Route::post('orders',                           [OrderController::class, 'store']);
        Route::get('orders/{id}',                       [OrderController::class, 'show']);
        Route::patch('orders/{id}/cancel',              [OrderController::class, 'cancel']);
        Route::get('orders/{id}/tracking',              [OrderController::class, 'track']);
        Route::post('orders/{id}/confirm-delivery',     [OrderController::class, 'confirmDelivery']);
        Route::post('orders/{id}/report-issue',         [OrderController::class, 'reportIssue']);
        Route::post('orders/{id}/reorder',              [OrderController::class, 'reorder']);
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
        });
    });

    // ============================================================
    // STORE DASHBOARD (owner)
    // ============================================================
    Route::group(['prefix' => 'store', 'middleware' => RoleMiddleware::class . ':restaurant_owner'], function () {
        Route::get('dashboard',                         [StoreDashboardController::class, 'index']);
        Route::put('profile',                           [StoreDashboardController::class, 'updateProfile']);
        Route::post('upload-image',                     [StoreDashboardController::class, 'uploadImage']);

        // Store Hours & Closures (PRD §7.1)
        Route::get('hours',                             [StoreDashboardController::class, 'getHours']);
        Route::put('hours',                             [StoreDashboardController::class, 'setHours']);
        Route::post('closures',                         [StoreDashboardController::class, 'addClosure']);
        Route::delete('closures/{id}',                  [StoreDashboardController::class, 'removeClosure']);

        // Menu (products) — admin-approval workflow (PRD §7.2)
        Route::get('menu',                              [StoreDashboardController::class, 'getMenu']);
        Route::post('menu',                             [StoreDashboardController::class, 'addProduct']);
        Route::put('menu/{id}',                         [StoreDashboardController::class, 'updateProduct']);
        Route::delete('menu/{id}',                      [StoreDashboardController::class, 'deleteProduct']);

        // Orders
        Route::get('orders',                            [StoreDashboardController::class, 'getOrders']);
        Route::get('orders/history',                    [StoreDashboardController::class, 'orderHistory']);
        Route::put('orders/{id}/accept',                [StoreDashboardController::class, 'acceptOrder']);
        Route::put('orders/{id}/ready',                 [StoreDashboardController::class, 'orderReady']);
        Route::put('orders/{id}/reject',                [StoreDashboardController::class, 'rejectOrder']);

        // Analytics (PRD §7.3)
        Route::get('analytics/revenue',                 [StoreDashboardController::class, 'revenueAnalytics']);
        Route::get('analytics/top-products',            [StoreDashboardController::class, 'topProducts']);
        Route::get('analytics/volume',                  [StoreDashboardController::class, 'volumeAnalytics']);
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
        Route::get('couriers/{id}/monthly-stats',       [AdminController::class, 'courierMonthlyStats']);

        // Stores
        Route::get('stores',                            [AdminController::class, 'stores']);
        Route::get('stores/pending',                    [AdminController::class, 'pendingStores']);
        Route::post('stores/{id}/approve',              [AdminController::class, 'approveStore']);
        Route::patch('stores/{id}/status',              [AdminController::class, 'updateStoreStatus']);

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

        // Categories
        Route::get('categories',                        [AdminController::class, 'categoriesList']);
        Route::post('categories',                       [AdminController::class, 'createCategory']);
        Route::put('categories/{id}',                   [AdminController::class, 'updateCategory']);

        // Settings
        Route::get('settings',                          [AdminController::class, 'settings']);
        Route::put('settings',                          [AdminController::class, 'updateSetting']);

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
