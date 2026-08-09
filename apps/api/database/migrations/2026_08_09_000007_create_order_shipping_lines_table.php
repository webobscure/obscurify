<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Order's immutable shipping snapshot — mirrors OrderItem/OrderAddress
     * exactly (spec section 14: "do not display historical orders from
     * current live ShippingMethod values"). Copied once from the selected
     * ShippingQuote at CompleteCheckout and never updated afterward, so a
     * later ShippingMethod price/name change can never change what a past
     * order reports.
     */
    public function up(): void
    {
        Schema::create('order_shipping_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();

            $table->string('provider');
            $table->string('service_code')->nullable();

            $table->string('title');

            $table->bigInteger('price_amount');
            $table->char('currency', 3);

            $table->unsignedInteger('estimated_days_min')->nullable();
            $table->unsignedInteger('estimated_days_max')->nullable();

            $table->timestamp('created_at');

            $table->unique('order_id');
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_shipping_lines');
    }
};
