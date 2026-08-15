<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The execution log/history (spec section 11: "Execution History",
     * "Execution Logs") — one row per condition evaluation or action run
     * within a WorkflowExecution, in `position` order. `workflow_action_id`
     * is null for the single condition-evaluation step every execution
     * records first.
     */
    public function up(): void
    {
        Schema::create('workflow_execution_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('workflow_execution_id')->constrained('workflow_executions')->cascadeOnDelete();
            $table->foreignUlid('workflow_action_id')->nullable()->constrained('workflow_actions')->nullOnDelete();

            // condition | action
            $table->string('step_type');
            // pending | succeeded | failed | skipped | waiting
            $table->string('status')->default('pending');

            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index(['workflow_execution_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_execution_steps');
    }
};
