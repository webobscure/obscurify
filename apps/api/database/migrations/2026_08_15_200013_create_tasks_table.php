<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs the "Create task" automation action (spec section 5) — a
     * minimal admin to-do item, not a project-management feature.
     * `related_type`/`related_id` is a manual polymorphic pair (same
     * convention as segment_rules.segmentable_*) pointing at whatever
     * the triggering execution's aggregate was (an Order, a Customer, ...).
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->string('related_type')->nullable();
            $table->ulid('related_id')->nullable();
            $table->foreignUlid('workflow_execution_id')->nullable()->constrained('workflow_executions')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
