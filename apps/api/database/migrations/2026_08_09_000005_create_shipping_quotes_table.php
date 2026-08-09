<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persisted only for the rate a checkout actually selects (spec
     * section 5: "do not necessarily persist every rate lookup") — GET
     * shipping-rates itself returns ephemeral ShippingRate value objects,
     * never written here. A row only exists once PATCH .../shipping picks
     * one. expires_at bounds how long that selection stays valid before
     * CompleteCheckout must reject it (spec section 12's "quote model"
     * policy) — see config('commerce.shipping.quote_ttl_minutes').
     */
    public function up(): void
    {
        Schema::create('shipping_quotes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('checkout_id')->constrained('checkouts')->cascadeOnDelete();
            $table->foreignUlid('shipping_method_id')->nullable()->constrained('shipping_methods')->nullOnDelete();

            $table->string('provider');
            $table->string('service_code')->nullable();
            $table->string('name');

            $table->bigInteger('price_amount');
            $table->char('currency', 3);

            $table->unsignedInteger('estimated_days_min')->nullable();
            $table->unsignedInteger('estimated_days_max')->nullable();

            $table->timestamp('expires_at');

            $table->jsonb('metadata')->nullable();

            $table->timestamp('created_at');

            $table->index('store_id');
            $table->index(['checkout_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_quotes');
    }
};
