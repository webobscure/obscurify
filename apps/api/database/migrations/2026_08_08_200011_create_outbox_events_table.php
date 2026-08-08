<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Minimal transactional outbox — ARCHITECTURE.md doesn't document one
     * yet, but the milestone spec asks for it and the underlying problem
     * is real: writing the row in the *same* DB transaction as the Order
     * it describes is what closes the "order committed, process crashes,
     * event lost" window, without needing an external broker.
     */
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('event_type');
            $table->string('aggregate_type');
            $table->ulid('aggregate_id');

            $table->jsonb('payload');

            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);

            $table->index('store_id');
            $table->index(['processed_at', 'occurred_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
