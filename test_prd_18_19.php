<?php

// Boot Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Store;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\OrderReport;
use App\Actions\Reviews\CreateProductReviews;
use App\Actions\Reviews\CreateOrUpdateStoreReview;
use App\Actions\Reviews\DeleteReview;
use App\Http\Payloads\Reviews\StoreProductReviewPayload;
use App\Http\Payloads\Reviews\ProductReviewItemPayload;
use App\Http\Payloads\Reviews\StoreStoreReviewPayload;
use App\Actions\Reports\CreateReport;
use App\Actions\Reports\ResolveReport;
use App\Http\Payloads\Reports\StoreReportPayload;
use App\Http\Payloads\Reports\ResolveReportPayload;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

echo "Starting PRD 18 (Reviews) & 19 (Reports) Validation Tests...\n";
echo "==========================================================\n\n";

$report = [];

DB::beginTransaction();
try {
    Model::unguard();
    // --- 1. SETUP TEST DATA ---

    // Create Admin User to own the store
    $storeOwner = User::create([
        'name' => 'Test Store Owner',
        'phone' => '+212600000001',
        'role' => 'restaurant_owner'
    ]);

    // Create Customer User
    $customer = User::create([
        'name' => 'Test Customer',
        'phone' => '+212600000002',
        'role' => 'customer'
    ]);

    // Create Address
    $address = \App\Models\Address::create([
        'user_id' => $customer->id,
        'label' => 'Home',
        'street' => '123 Test St',
        'city' => 'Casablanca',
        'location' => DB::raw("ST_GeomFromText('POINT(-7.5898 33.5731)', 4326)"),
    ]);

    // Create Category
    $category = \App\Models\Category::create([
        'name' => 'Test Category',
        'slug' => 'test-category',
        'icon' => 'test.png',
        'is_active' => true,
    ]);

    // Create Store
    $store = Store::create([
        'user_id' => $storeOwner->id,
        'name' => 'Test Store',
        'category_id' => $category->id,
        'location' => DB::raw("ST_GeomFromText('POINT(-7.5898 33.5731)', 4326)"),
    ]);

    // Create Product
    $product = Product::create([
        'restaurant_id' => $store->id,
        'category' => 'Food',
        'name' => 'Test Product',
        'price' => 50.00,
        'is_available' => true,
    ]);

    // Create Delivered Order
    $order = Order::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id, 
        'total_price' => 50.00,
        'status' => 'delivered',
        'address_id' => $address->id,
        'delivery_distance_km' => 2.5,
    ]);

    // Create Order Item
    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => 50.00,
    ]);

    echo "[SETUP] Test data created successfully.\n\n";

    // --- 2. TEST PRODUCT REVIEWS (PRD 18.1) ---
    echo "Testing Product Reviews (PRD 18.1)...\n";
    $productAction = new CreateProductReviews();
    
    // Test 2.1: Valid Product Review
    $payload = new StoreProductReviewPayload($order->id, [
        new ProductReviewItemPayload($orderItem->id, 5, 'Great product!')
    ]);
    
    $reviews = $productAction->handle($payload, $customer->id);
    if (count($reviews) === 1 && $reviews[0]->rating === 5) {
        echo "✅ Valid Product Review creation passed.\n";
        $report[] = "[PRD 18.1] Product Review Creation: PASS";
    } else {
        echo "❌ Valid Product Review creation failed.\n";
        $report[] = "[PRD 18.1] Product Review Creation: FAIL";
    }

    // Test 2.2: Duplicate Product Review Guard
    try {
        $productAction->handle($payload, $customer->id);
        echo "❌ Duplicate Product Review guard failed (Exception expected).\n";
        $report[] = "[PRD 18.1] Duplicate Product Review Guard: FAIL";
    } catch (ValidationException $e) {
        echo "✅ Duplicate Product Review guard passed (Caught ValidationException).\n";
        $report[] = "[PRD 18.1] Duplicate Product Review Guard: PASS";
    }

    // --- 3. TEST STORE REVIEWS (PRD 18.2) ---
    echo "\nTesting Store Reviews (PRD 18.2)...\n";
    $storeAction = new CreateOrUpdateStoreReview();

    // Test 3.1: Valid Store Review (Customer has delivered order)
    $storePayload = new StoreStoreReviewPayload($store->id, 4, 'Good store!');
    $storeReview = $storeAction->handle($storePayload, $customer->id);
    if ($storeReview->rating === 4) {
        echo "✅ Valid Store Review creation passed.\n";
        $report[] = "[PRD 18.2] Store Review Creation: PASS";
    }

    // Test 3.2: Store Review Update (Upsert)
    $storePayloadUpdate = new StoreStoreReviewPayload($store->id, 5, 'Updated to excellent!');
    $updatedStoreReview = $storeAction->handle($storePayloadUpdate, $customer->id);
    if ($updatedStoreReview->id === $storeReview->id && $updatedStoreReview->rating === 5) {
        echo "✅ Store Review update (upsert) passed.\n";
        $report[] = "[PRD 18.2] Store Review Update: PASS";
    } else {
         echo "❌ Store Review update (upsert) failed.\n";
         $report[] = "[PRD 18.2] Store Review Update: FAIL";
    }

    // Test 3.3: Store Review Guard (No delivered orders)
    $customer2 = User::create([
        'name' => 'Customer With No Orders',
        'phone' => '+212600000003',
        'role' => 'customer'
    ]);
    try {
        $storeAction->handle($storePayload, $customer2->id);
        echo "❌ Store Review guard (No delivered orders) failed (Exception expected).\n";
        $report[] = "[PRD 18.2] Store Review Delivery Guard: FAIL";
    } catch (ValidationException $e) {
        echo "✅ Store Review guard (No delivered orders) passed (Caught ValidationException).\n";
        $report[] = "[PRD 18.2] Store Review Delivery Guard: PASS";
    }

    // --- 4. TEST DELETE REVIEW (PRD 18.5) ---
    echo "\nTesting Admin Delete Review (PRD 18.5)...\n";
    $deleteAction = new DeleteReview();
    $deleteAction->handle($updatedStoreReview->id);
    
    // Verify it was soft deleted
    $checkReview = Review::withTrashed()->find($updatedStoreReview->id);
    if ($checkReview->trashed()) {
        echo "✅ Admin Review deletion (Soft Delete) passed.\n";
        $report[] = "[PRD 18.5] Admin Review Soft Delete: PASS";
    }

    // --- 5. TEST ORDER REPORTS (PRD 19) ---
    echo "\nTesting Order Reports (PRD 19)...\n";
    $reportAction = new CreateReport();
    $resolveAction = new ResolveReport();

    // Create active order
    $activeOrder = Order::create([
        'customer_id' => $customer->id,
        'store_id' => $store->id,
        'total_price' => 20.00,
        'status' => 'preparing',
        'address_id' => $address->id,
        'delivery_distance_km' => 1.5,
    ]);

    // Test 5.1: Create Report
    $reportPayload = new StoreReportPayload($activeOrder->id, 'late_delivery', 'Food taking too long');
    $orderReport = $reportAction->handle($reportPayload, $customer->id);
    if ($orderReport->status === 'open' && $orderReport->type === 'late_delivery') {
        echo "✅ Order Report creation passed.\n";
        $report[] = "[PRD 19] Order Report Creation: PASS";
    }

    // Test 5.2: Duplicate Report Guard
    try {
        $reportAction->handle($reportPayload, $customer->id);
        echo "❌ Duplicate Report guard failed (Exception expected).\n";
        $report[] = "[PRD 19] Duplicate Report Guard: FAIL";
    } catch (ValidationException $e) {
        echo "✅ Duplicate Report guard passed (Caught ValidationException).\n";
        $report[] = "[PRD 19] Duplicate Report Guard: PASS";
    }

    // Test 5.3: Resolve Report
    $resolvePayload = new ResolveReportPayload('Spoke to store', 'store_warned');
    $resolvedReport = $resolveAction->handle($orderReport->id, $resolvePayload);
    if ($resolvedReport->status === 'resolved' && !is_null($resolvedReport->resolved_at)) {
        echo "✅ Admin Report Resolution passed.\n";
        $report[] = "[PRD 19] Admin Report Resolution: PASS";
    }

    echo "\n==========================================================\n";
    echo "TESTING COMPLETE.\n";

} catch (\Exception $e) {
    echo "\n❌ AN ERROR OCCURRED DURING TESTING:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    // Rollback DB so we don't leave junk in your actual dev database!
    DB::rollBack();
    echo "\nDatabase rolled back successfully to keep it clean.\n";
}

// Generate the final output file
file_put_contents('test_report_output.txt', implode("\n", $report));
