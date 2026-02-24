<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('address')->nullable();
            // TECHNICAL CONSTRAINT: PostGIS geography POINT type with GIST index
            $table->geography('location', 'point', 4326)->nullable();
            $table->string('phone')->nullable();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->decimal('rating', 3, 2)->default(0);
            $table->boolean('is_open')->default(true);
            $table->boolean('is_approved')->default(false);
            $table->unsignedInteger('delivery_fee')->default(10);
            $table->unsignedInteger('estimated_time')->default(30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
