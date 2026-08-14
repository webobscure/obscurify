<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One placed section on a PageLayout — the relational counterpart
     * of one entry in PageVersion.sections's jsonb array
     * (`{id, section_handle, settings, blocks}`). `section_handle`
     * references a ThemeSection *type* row by handle (never an FK — the
     * active theme can change independently of a page's content, the
     * same leniency ThemeRenderer::resolveSection() already has for an
     * unknown handle). `position` is the drag-and-drop order; reordering
     * is a bulk position rewrite (see SaveBuilderLayout), not a linked
     * list, since a page's section count is always small.
     */
    public function up(): void
    {
        Schema::create('section_instances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('page_layout_id')->constrained('page_layouts')->cascadeOnDelete();

            $table->string('section_handle');
            $table->unsignedInteger('position')->default(0);
            $table->json('settings');

            $table->timestamps();

            $table->index(['page_layout_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_instances');
    }
};
