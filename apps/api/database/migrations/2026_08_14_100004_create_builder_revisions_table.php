<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An immutable, append-only snapshot of a PageLayout's full
     * `sections` array at one point in time — every save (manual or
     * autosaved) creates one. This is what backs both the undo/redo
     * stack (BuilderHistory just points at a position in this sequence)
     * and the "Revision timeline" UI (spec sections 9/15) — a
     * *within-draft* history distinct from PageVersion's own
     * publish-time snapshots (ADR-019/020's whole-theme/whole-page
     * versioning is about published vs. draft; this is about undoing a
     * change made *while still drafting*, something Theme/CMS never
     * needed). `sequence` is a per-PageLayout monotonic counter — plain
     * `created_at` ordering is not reliable enough to guarantee undo/redo
     * moves exactly one step even under coarse timestamp resolution.
     */
    public function up(): void
    {
        Schema::create('builder_revisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('page_layout_id')->constrained('page_layouts')->cascadeOnDelete();

            $table->unsignedInteger('sequence');
            $table->json('sections');

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['page_layout_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_revisions');
    }
};
