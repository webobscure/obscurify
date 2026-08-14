<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per PageLayout — the undo/redo cursor. `current_revision_id`
     * points at whichever BuilderRevision the layout currently reflects;
     * undo/redo simply moves this pointer to the adjacent `sequence` and
     * restores SectionInstance/BlockInstance (and PageVersion.sections)
     * from that revision's snapshot (see UndoBuilderLayout/
     * RedoBuilderLayout). Saving a *new* change after an undo does not
     * delete the "future" revisions it stepped back from — they simply
     * stop being reachable by redo once a new revision is appended past
     * the current position, the same branch-is-abandoned-not-deleted
     * semantics most editors use.
     */
    public function up(): void
    {
        Schema::create('builder_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('page_layout_id')->unique()->constrained('page_layouts')->cascadeOnDelete();
            $table->foreignUlid('current_revision_id')->nullable()->constrained('builder_revisions')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_histories');
    }
};
