<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only return timeline — mirrors fulfillment_events/
     * tracking_events exactly. `type` is a free string (not an enum
     * table); the state machine itself lives in ReturnStateMachine / the
     * `status` column on `return_requests`.
     */
    public function up(): void
    {
        Schema::create('return_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('return_request_id')->constrained('return_requests')->cascadeOnDelete();

            $table->string('type');
            $table->text('description')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamp('created_at');

            $table->index('store_id');
            $table->index(['return_request_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_events');
    }
};
