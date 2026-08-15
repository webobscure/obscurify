<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Analytics' own append-only, normalized event log — the ONLY thing
     * AnalyticsProjector ever writes, and (besides analytics_snapshots)
     * the only thing every downstream consumer (AnalyticsAggregator,
     * AnalyticsSnapshotBuilder, every widget/report read) ever reads.
     * Spec section 2: "Analytics must consume Platform Events only.
     * Never query commerce tables directly inside widgets."
     *
     * The projector legitimately reads the triggering commerce
     * aggregate exactly once, at the moment its OutboxEvent is
     * processed, to build this row's `payload` (line items, discount
     * applications, shipping line, ...) — after that single read,
     * nothing downstream ever touches a commerce table again. See
     * docs/adr/026-analytics-platform.md.
     *
     * Idempotent via the unique constraint on `outbox_event_id` — the
     * same claim-or-skip pattern WebhookDelivery/WorkflowExecution use
     * for their own fan-out from the same outbox stream.
     */
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('outbox_event_id')->constrained('outbox_events')->cascadeOnDelete();

            $table->string('event_type');
            $table->timestamp('occurred_at');
            $table->string('aggregate_type');
            $table->ulid('aggregate_id');
            $table->ulid('customer_id')->nullable();

            $table->bigInteger('amount')->nullable();
            $table->string('currency', 3)->nullable();
            $table->json('payload')->nullable();

            $table->timestamps();

            $table->unique('outbox_event_id');
            $table->index('store_id');
            $table->index(['store_id', 'event_type', 'occurred_at']);
            $table->index(['store_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
