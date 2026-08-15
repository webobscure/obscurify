<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (workflow_version, outbox_event) — the unique
     * constraint is DispatchWorkflowTriggersForEvent's idempotency claim,
     * the exact same pattern WebhookDelivery uses for its own fan-out
     * (see docs/adr/025-automation-engine.md). `depth`/`root_execution_id`/
     * `caused_by_execution_id` are the loop-prevention bookkeeping (spec
     * section 13): an action that itself records an outbox event (e.g.
     * "add customer tag") tags the resulting OutboxEvent's originating
     * execution, so a workflow that (directly or transitively) triggers
     * itself again can be refused once `depth` exceeds
     * WorkflowLoopGuard::MAX_DEPTH instead of recursing forever.
     * `next_retry_at`/`next_resume_at` mirror WebhookDelivery's own
     * explicit retry-state columns rather than relying on Laravel's
     * queue-level $tries/backoff, for the same reason DeliverWebhookJob
     * does: retry timing must survive across job dispatches, not just
     * queue-worker attempts.
     */
    public function up(): void
    {
        Schema::create('workflow_executions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->foreignUlid('workflow_version_id')->constrained('workflow_versions')->cascadeOnDelete();
            $table->foreignUlid('outbox_event_id')->constrained('outbox_events')->cascadeOnDelete();

            // pending | running | waiting | completed | failed | dead_letter
            $table->string('status')->default('pending');

            $table->json('context')->default('{}');
            $table->unsignedSmallInteger('current_action_position')->default(0);

            $table->unsignedSmallInteger('depth')->default(0);
            $table->ulid('root_execution_id')->nullable();
            $table->ulid('caused_by_execution_id')->nullable();

            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('next_resume_at')->nullable();
            $table->string('wait_until_event_type')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->unique(['workflow_version_id', 'outbox_event_id']);
            $table->index(['status', 'next_resume_at']);
            $table->index(['status', 'next_retry_at']);
            $table->index('root_execution_id');
        });

        Schema::table('workflow_executions', function (Blueprint $table) {
            $table->foreign('root_execution_id')->references('id')->on('workflow_executions')->nullOnDelete();
            $table->foreign('caused_by_execution_id')->references('id')->on('workflow_executions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_executions');
    }
};
