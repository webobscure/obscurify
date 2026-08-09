<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only fulfillment timeline (spec section 17) — mirrors
     * TrackingEvent's shape exactly. `type` is a free string (not an enum
     * table) matching FulfillmentEvent's role as a human-readable log, not
     * a state-machine input; the state machine itself lives in
     * FulfillmentStateMachine / the `status` column on `fulfillments`.
     */
    public function up(): void
    {
        Schema::create('fulfillment_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('fulfillment_id')->constrained('fulfillments')->cascadeOnDelete();

            $table->string('type');
            $table->text('description')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamp('created_at');

            $table->index('store_id');
            $table->index(['fulfillment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_events');
    }
};
