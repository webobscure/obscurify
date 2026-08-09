<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supports partial shipment (spec section 17): quantity may be less
     * than the OrderItem's own quantity, and one OrderItem may appear
     * across multiple Shipments. "Total shipped quantity never exceeds
     * ordered quantity" is enforced in CreateShipment under a row lock on
     * the OrderItem, not by a DB constraint — Postgres has no clean way to
     * express a cross-row SUM() constraint, so the lock is the real guard
     * (see the concurrency test for two simultaneous shipment-create
     * requests).
     */
    public function up(): void
    {
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignUlid('order_item_id')->constrained('order_items')->cascadeOnDelete();

            $table->unsignedInteger('quantity');

            $table->timestamp('created_at');

            $table->index('store_id');
            $table->index('shipment_id');
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
    }
};
