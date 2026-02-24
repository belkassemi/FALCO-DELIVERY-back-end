<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('label')->default('Home');
            $table->string('street');
            $table->string('city')->nullable();
            
            // TECHNICAL CONSTRAINT: PostGIS geography POINT type with GIST index
            $table->geography('location', 'point', 4326)->nullable();
            
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        \Illuminate\Support\Facades\DB::statement('CREATE INDEX addresses_location_gist ON addresses USING GIST (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
