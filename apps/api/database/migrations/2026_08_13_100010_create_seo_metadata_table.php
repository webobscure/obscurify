<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SEO fields for one subject, keyed by `subject_type`/`subject_id` —
     * the same "not true DB polymorphism" pattern menu_items' target
     * columns use (ADR-018's owner_type/owner_id precedent), chosen so
     * adding a new subject type (Product, Collection) later is a new enum
     * case, not a schema change. `subject_id` for a page points at a
     * PageVersion, not a Page — SEO metadata is part of what gets frozen
     * at publish time, same as `sections`, so it must snapshot with the
     * version it describes rather than being edited out from under an
     * already-published page.
     */
    public function up(): void
    {
        Schema::create('seo_metadata', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('subject_type');
            $table->ulid('subject_id');
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('og_image')->nullable();

            $table->timestamps();

            $table->unique(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_metadata');
    }
};
