<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Order's discount snapshot (spec section 8: "do not depend on
     * future promotion changes") — one row per applied PromotionAction,
     * carrying its own promotion_name/code copy so a later rename/delete
     * of the Promotion or DiscountCode never changes what an existing
     * Order displays. promotion_id/discount_code_id are kept nullable +
     * nullOnDelete purely for FK integrity of the (never-exposed) delete
     * path; the snapshot columns are what rendering always reads from.
     * No UPDATED_AT — immutable like OrderItem.
     */
    public function up(): void
    {
        Schema::create('discount_applications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->foreignUlid('discount_code_id')->nullable()->constrained('discount_codes')->nullOnDelete();
            $table->foreignUlid('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();

            $table->string('promotion_name');
            $table->string('code')->nullable();
            $table->string('action_type');
            $table->string('target');
            $table->bigInteger('amount');
            $table->string('currency');

            $table->timestamp('created_at')->useCurrent();

            $table->index('store_id');
            $table->index('order_id');
            $table->index('promotion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_applications');
    }
};
