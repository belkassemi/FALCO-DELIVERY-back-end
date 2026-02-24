<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', [
                'pending', 'assigned', 'preparing',
                'on_the_way', 'delivered', 'cancelled', 'rejected'
            ])->default('pending');
            $table->decimal('total_price', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(10);
            $table->integer('estimated_time')->default(30); // minutes
            $table->string('cancel_reason')->nullable();
            $table->string('payment_method')->default('cash');
            $table->enum('payment_status', ['pending', 'paid', 'refunded'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
