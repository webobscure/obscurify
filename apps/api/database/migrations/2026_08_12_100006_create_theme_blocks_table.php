<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A block TYPE definition (e.g. "button", "image", "accordion"),
     * scoped to the ThemeSection type that allows it — mirrors
     * theme_sections exactly, one level down. Reorderability (spec
     * section 6) happens at the *instance* level, inside
     * ThemeTemplate.sections[].blocks (jsonb) — this table only defines
     * what block types exist and their schema.
     */
    public function up(): void
    {
        Schema::create('theme_blocks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('theme_version_id')->constrained('theme_versions')->cascadeOnDelete();
            $table->foreignUlid('theme_section_id')->constrained('theme_sections')->cascadeOnDelete();

            $table->string('handle');
            $table->string('name');
            $table->json('schema');

            $table->timestamps();

            $table->unique(['theme_section_id', 'handle']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_blocks');
    }
};
