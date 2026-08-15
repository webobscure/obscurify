<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links an OutboxEvent recorded as the side effect of an automation
     * action back to the WorkflowExecution that produced it —
     * WorkflowLoopGuard's cycle-detection chain (spec section 13). Added
     * after workflow_executions rather than in the same migration as
     * outbox_events (a Shared\Commerce table, predating this domain by
     * many milestones) since the FK target didn't exist yet.
     */
    public function up(): void
    {
        Schema::table('outbox_events', function (Blueprint $table) {
            $table->foreignUlid('caused_by_workflow_execution_id')
                ->nullable()
                ->after('attempts')
                ->constrained('workflow_executions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('outbox_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('caused_by_workflow_execution_id');
        });
    }
};
