<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_change_requests', function (Blueprint $table) {
            $table->json('old_data')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('menu_change_requests', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->dropColumn(['old_data', 'requested_by']);
        });
    }
};
