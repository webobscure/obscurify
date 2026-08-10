<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only redemption ledger, one row per Promotion actually
     * applied to a completed Order (spec section 13: usage limits,
     * single-use coupons, concurrency) — written inside CompleteCheckout's
     * transaction, after locking the DiscountCode row (when present) and
     * verifying usage_limit/per_customer_limit still hold. No
     * UPDATED_AT — immutable like OrderItem/ReturnEvent.
     */
    public function up(): void
    {
        Schema::create('promotion_usages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignUlid('discount_code_id')->nullable()->constrained('discount_codes')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->bigInteger('amount');

            $table->timestamp('created_at')->useCurrent();

            $table->index('store_id');
            $table->index('promotion_id');
            $table->index('discount_code_id');
            $table->index('customer_id');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_usages');
    }
};
