<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A flat, ordered sequence per version (spec explicitly excludes a
     * visual BPMN editor — no branching, just a linear list run in
     * `position` order). `type` is a fixed WorkflowActionType case, or
     * `app_action` for an app-registered action (config then references
     * the contributing AppExtension — see WorkflowActionExecutor). A
     * `delay` action is a step like any other; the runner pauses the
     * whole execution at that position and resumes it later rather than
     * treating delay as structurally different (spec section 6).
     */
    public function up(): void
    {
        Schema::create('workflow_actions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('workflow_version_id')->constrained('workflow_versions')->cascadeOnDelete();
            $table->string('type');
            $table->json('config')->default('{}');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('store_id');
            $table->index(['workflow_version_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_actions');
    }
};
