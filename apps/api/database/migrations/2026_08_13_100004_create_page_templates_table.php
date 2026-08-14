<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A named, reusable starting layout for a new page — a plain preset
     * library, deliberately store-scoped and NOT tied to any specific
     * theme version (unlike ThemeSection/ThemeBlock): `sections` is
     * copied into a new PageVersion's own `sections` column at creation
     * time, never referenced afterward, so editing or deleting a
     * PageTemplate can never retroactively change a page that started
     * from it — the same "whole-snapshot, not a live reference"
     * principle ADR-019 established for theme versioning.
     */
    public function up(): void
    {
        Schema::create('page_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('name');
            $table->json('sections');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_templates');
    }
};
