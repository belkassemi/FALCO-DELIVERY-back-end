<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rename menu_items → products
        Schema::rename('menu_items', 'products');

        // 2. Rename menu_item_id FK in order_items
        if (Schema::hasColumn('order_items', 'menu_item_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->renameColumn('menu_item_id', 'product_id');
            });
        }

        // 3. Rename menu_item_id FK in menu_change_requests
        if (Schema::hasColumn('menu_change_requests', 'menu_item_id')) {
            Schema::table('menu_change_requests', function (Blueprint $table) {
                $table->renameColumn('menu_item_id', 'product_id');
            });
        }

        // 4. Add is_age_restricted column, remove requires_prescription if exists
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_age_restricted')->default(false)->after('is_available');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_age_restricted');
        });

        Schema::rename('products', 'menu_items');

        if (Schema::hasColumn('order_items', 'product_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->renameColumn('product_id', 'menu_item_id');
            });
        }

        if (Schema::hasColumn('menu_change_requests', 'product_id')) {
            Schema::table('menu_change_requests', function (Blueprint $table) {
                $table->renameColumn('product_id', 'menu_item_id');
            });
        }
    }
};
