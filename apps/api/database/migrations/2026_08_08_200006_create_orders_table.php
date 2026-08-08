<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->unsignedBigInteger('number');

            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignUlid('checkout_id')->nullable()->constrained('checkouts')->nullOnDelete();

            $table->char('currency', 3);
            $table->bigInteger('items_subtotal_amount');
            $table->bigInteger('shipping_amount');
            $table->bigInteger('discount_amount');
            $table->bigInteger('tax_amount');
            $table->bigInteger('total_amount');

            $table->string('order_status')->default('open');
            $table->string('financial_status')->default('pending');
            $table->string('fulfillment_status')->default('unfulfilled');

            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->timestamps();
            $table->timestamp('cancelled_at')->nullable();

            $table->unique(['store_id', 'number']);
            $table->index('store_id');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
