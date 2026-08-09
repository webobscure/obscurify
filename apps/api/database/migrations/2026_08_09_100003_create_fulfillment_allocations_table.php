<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * inventory_reservation_id (nullable) records exactly which reservation
     * this allocation is drawing from — needed so completion knows which
     * InventoryReservation to (partially) consume, and so a second
     * fulfillment attempt against the same order can see how much of a
     * reservation is already claimed. Null only for non-tracked
     * InventoryItems (see AllocateFulfillment), which have no reservation
     * to draw from.
     *
     * consumed_at / cancelled_at are set once, never both — an allocation
     * is consumed (fulfillment completed, stock actually left) xor
     * cancelled (fulfillment cancelled before completion); rows are never
     * deleted, matching the "never mutate history" requirement for the
     * inventory movements this table drives (spec section 8).
     */
    public function up(): void
    {
        Schema::create('fulfillment_allocations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('fulfillment_item_id')->constrained('fulfillment_items')->cascadeOnDelete();

            $table->foreignUlid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignUlid('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignUlid('inventory_reservation_id')->nullable()
                ->constrained('inventory_reservations')->cascadeOnDelete();

            $table->unsignedInteger('quantity');

            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamp('created_at');

            $table->index('store_id');
            $table->index('fulfillment_item_id');
            $table->index('inventory_reservation_id');
            $table->index(['inventory_item_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_allocations');
    }
};
