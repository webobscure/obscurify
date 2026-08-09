<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * picked_quantity <= quantity and packed_quantity <= picked_quantity
     * are enforced in application code (PickFulfillmentItems /
     * PackFulfillmentItems under row locks), not as DB CHECK constraints —
     * consistent with how ShipmentItem's "total shipped <= ordered"
     * invariant is enforced (row lock, not a cross-row constraint).
     */
    public function up(): void
    {
        Schema::create('fulfillment_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('fulfillment_id')->constrained('fulfillments')->cascadeOnDelete();
            $table->foreignUlid('order_item_id')->constrained('order_items')->cascadeOnDelete();

            $table->unsignedInteger('quantity');
            $table->unsignedInteger('picked_quantity')->default(0);
            $table->unsignedInteger('packed_quantity')->default(0);

            $table->timestamps();

            $table->index('store_id');
            $table->index('fulfillment_id');
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_items');
    }
};
