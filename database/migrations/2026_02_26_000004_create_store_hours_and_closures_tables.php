<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->tinyInteger('day_of_week'); // 0=Sunday ... 6=Saturday
            $table->time('open_time');
            $table->time('close_time');
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['store_id', 'day_of_week']);
        });

        Schema::create('store_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->date('closed_date');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'closed_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_closures');
        Schema::dropIfExists('store_hours');
    }
};
