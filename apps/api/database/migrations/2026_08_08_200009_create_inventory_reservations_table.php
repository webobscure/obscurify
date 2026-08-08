<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * location_id is deliberately NOT nullable (spec sketch listed it as
     * nullable): allocation is split across locations deterministically
     * (see ReserveInventory), one row per location actually touched, so
     * every reservation always has a concrete location — see
     * "Multi-location allocation strategy" in the milestone report.
     */
    public function up(): void
    {
        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->foreignUlid('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignUlid('location_id')->constrained('locations')->cascadeOnDelete();

            $table->foreignUlid('checkout_id')->constrained('checkouts')->cascadeOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->unsignedInteger('quantity');
            $table->string('status')->default('active');

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('consumed_at')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index(['inventory_item_id', 'location_id']);
            $table->index('checkout_id');
            $table->index('order_id');
            // Used by inventory:release-expired-reservations to find work
            // without scanning the whole table.
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
    }
};
