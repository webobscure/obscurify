<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only version history — editing a never-published workflow
     * mutates its single existing draft version in place; editing an
     * already-published workflow creates a new draft version instead of
     * touching the published one (see UpdateWorkflow / ADR-025). Rollback
     * clones an old version's trigger/conditions/actions into a brand new
     * version rather than un-archiving history, so version_number is
     * strictly increasing and no version is ever mutated after it stops
     * being the current draft.
     */
    public function up(): void
    {
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->unsignedInteger('version_number');

            // draft | published | archived (per-version; workflows.published_version_id
            // is the only row across a workflow's versions ever "published").
            $table->string('status')->default('draft');

            $table->timestamps();

            $table->index('store_id');
            $table->unique(['workflow_id', 'version_number']);
        });

        // Self-referencing FK from workflows onto this table, deferred
        // here since workflow_versions.workflow_id must exist first (the
        // same two-step precedent as segment_rules.parent_id).
        Schema::table('workflows', function (Blueprint $table) {
            $table->foreign('published_version_id')->references('id')->on('workflow_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropForeign(['published_version_id']);
        });

        Schema::dropIfExists('workflow_versions');
    }
};
