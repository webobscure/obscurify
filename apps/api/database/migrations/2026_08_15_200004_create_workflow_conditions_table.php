<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same tree-node shape as segment_rules (M18) — a *condition* node
     * (`variable_key`/`operator`/`value` set, `boolean_operator` null) or
     * a *group* node (`boolean_operator` set, has children via
     * `parent_id`) — deliberately a fresh table rather than reusing
     * segment_rules, since a workflow condition's `variable_key` resolves
     * against a trigger's Context (order/payment/customer/... — see
     * WorkflowVariableResolver) instead of segment_rules' fixed Customer/
     * CustomerMetric field set; see docs/adr/025-automation-engine.md for
     * why this isn't the same table.
     */
    public function up(): void
    {
        Schema::create('workflow_conditions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('workflow_version_id')->constrained('workflow_versions')->cascadeOnDelete();
            $table->ulid('parent_id')->nullable();

            $table->string('boolean_operator')->nullable();
            $table->string('variable_key')->nullable();
            $table->string('operator')->nullable();
            $table->json('value')->nullable();

            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('store_id');
            $table->index('workflow_version_id');
            $table->index('parent_id');
        });

        Schema::table('workflow_conditions', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('workflow_conditions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_conditions');
    }
};
