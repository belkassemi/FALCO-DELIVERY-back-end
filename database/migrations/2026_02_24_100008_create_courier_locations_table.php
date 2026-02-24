<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained('users')->onDelete('cascade');
            
            // TECHNICAL CONSTRAINT: PostGIS geography POINT type with GIST index
            $table->geography('location', 'point', 4326)->nullable();
            
            $table->boolean('is_online')->default(false);
            $table->string('vehicle_type')->default('bike');
            $table->timestamps();
            $table->unique('courier_id');
        });

        // Add GIST index for the geography column
        DB::statement('CREATE INDEX courier_locations_location_gist ON courier_locations USING GIST (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_locations');
    }
};
