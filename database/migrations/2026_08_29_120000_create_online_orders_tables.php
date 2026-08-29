<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status', 20)->default('pending');
            $table->string('fulfillment_type', 20);
            $table->string('phone', 40);
            $table->string('customer_name', 120)->nullable();
            $table->string('street', 160)->nullable();
            $table->string('address_detail', 120)->nullable();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method', 20)->nullable();
            $table->bigInteger('pays_with')->nullable();
            $table->text('notes')->nullable();
            $table->bigInteger('items_total')->default(0);
            $table->bigInteger('delivery_fee')->default(0);
            $table->bigInteger('total')->default(0);
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->string('rejection_reason', 300)->nullable();
            $table->text('response_message')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('online_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->unsignedInteger('qty');
            $table->bigInteger('unit_price');
            $table->string('notes', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_order_items');
        Schema::dropIfExists('online_orders');
    }
};
