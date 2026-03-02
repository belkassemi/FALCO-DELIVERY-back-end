<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * PRD §18 — Reviews System Schema Update
 *
 * Requirements:
 * 1. Supports two distinct review types: 'store' and 'product'
 * 2. Renames `customer_id` -> `user_id` and `restaurant_id` -> `store_id` (matches broader PRD term)
 * 3. Removes the strict `order_id` uniqueness since one order can have multiple product reviews.
 * 4. Adds unique constraints based on types.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop existing constraints (Foreign keys and Unique)
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign('reviews_restaurant_id_foreign');
            $table->dropUnique(['order_id']);
            $table->dropColumn(['customer_id', 'store_id']);
        });

        // 2. Add new PRD compliant columns
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('user_id')->after('order_id')->constrained('users')->cascadeOnDelete();
            
            // `store_id` is nullable (only used for 'store' type reviews)
            $table->foreignId('store_id')->nullable()->after('user_id')->constrained('stores')->cascadeOnDelete();
            
            // `order_item_id` is nullable (only used for 'product' type reviews)
            $table->foreignId('order_item_id')->nullable()->after('store_id')->constrained('order_items')->nullOnDelete();
            
            $table->enum('type', ['store', 'product'])->after('order_id')->default('store');

            // 3. Add new unique constraints per PRD §18.3
            // A user can only leave one review per store (type=store)
            $table->unique(['user_id', 'store_id'], 'user_store_unique');
            
            // A user can only leave one review per specific order item (type=product)
            $table->unique(['user_id', 'order_item_id'], 'user_order_item_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique('user_store_unique');
            $table->dropUnique('user_order_item_unique');
            
            $table->dropForeign(['user_id']);
            $table->dropForeign(['store_id']);
            $table->dropForeign(['order_item_id']);

            $table->dropColumn(['user_id', 'store_id', 'order_item_id', 'type']);
            
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->unique('order_id');
        });
    }
};
