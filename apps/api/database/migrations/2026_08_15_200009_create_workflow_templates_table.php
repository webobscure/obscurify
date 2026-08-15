<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Starter workflows (spec section 9) — a global catalog, same
     * reasoning as workflow_variables (not tenant-scoped; apps register
     * templates via AppExtension). `definition` is a portable jsonb blob
     * shaped like {trigger: {...}, conditions: [...], actions: [...]} —
     * the same shape InstantiateWorkflowFromTemplate reads to create a
     * real Workflow/WorkflowVersion/WorkflowTrigger/WorkflowCondition/
     * WorkflowAction row set for a specific store.
     */
    public function up(): void
    {
        Schema::create('workflow_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->json('definition');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_templates');
    }
};
