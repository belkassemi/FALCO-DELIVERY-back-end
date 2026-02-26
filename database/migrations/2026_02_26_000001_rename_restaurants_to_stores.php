<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rename restaurants table → stores
        Schema::rename('restaurants', 'stores');

        // 2. Rename restaurant_id FK in related tables
        $tables = ['orders', 'menu_change_requests', 'reviews', 'favorites'];
        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'restaurant_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->renameColumn('restaurant_id', 'store_id');
                });
            }
        }

        // 3. Rename GIST index
        DB::statement('ALTER INDEX IF EXISTS restaurants_location_gist RENAME TO stores_location_gist');
    }

    public function down(): void
    {
        Schema::rename('stores', 'restaurants');

        $tables = ['orders', 'menu_change_requests', 'reviews', 'favorites'];
        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'store_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->renameColumn('store_id', 'restaurant_id');
                });
            }
        }

        DB::statement('ALTER INDEX IF EXISTS stores_location_gist RENAME TO restaurants_location_gist');
    }
};
