<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An immutable-once-published content snapshot — the exact
     * draft/publish/rollback lifecycle ThemeVersion established (ADR-019):
     * exactly one `draft` version per page is the live working copy;
     * publishing freezes it (status -> published, published_at set) and
     * opens a fresh draft cloned from it. `sections` is the same
     * instance-array shape ThemeTemplate.sections uses
     * (`{id, section_handle, settings, blocks}[]`), resolved against
     * whatever theme version is currently active for the store at render
     * time — a page reuses the storefront's own section/block type
     * definitions rather than inventing a second set, so a merchant can
     * drop the same section types onto a page as onto any theme template.
     * `created_from_version_id` records lineage, same reason as
     * theme_versions' own column.
     */
    public function up(): void
    {
        Schema::create('page_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('page_id')->constrained('pages')->cascadeOnDelete();
            $table->ulid('created_from_version_id')->nullable();

            $table->unsignedInteger('version_number');
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->json('sections');

            $table->timestamps();

            $table->unique(['page_id', 'version_number']);
            $table->index('store_id');
            $table->index(['page_id', 'status']);
        });

        Schema::table('page_versions', function (Blueprint $table) {
            $table->foreign('created_from_version_id')->references('id')->on('page_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_versions');
    }
};
