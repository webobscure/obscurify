<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A section TYPE definition (e.g. "hero", "featured-products") — the
     * JSON schema a merchant's instance settings are validated against
     * (spec section 8: "Every section exposes: settings, defaults,
     * presets, validation... do not hardcode configuration in Vue").
     * `schema` is an array of setting field definitions (id/type/label/
     * default); `presets` is a map of named default configurations. An
     * actual placement of this section type on a template, with concrete
     * instance values, lives in ThemeTemplate.sections (jsonb) — see
     * that migration.
     */
    public function up(): void
    {
        Schema::create('theme_sections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('theme_version_id')->constrained('theme_versions')->cascadeOnDelete();

            $table->string('handle');
            $table->string('name');
            $table->json('schema');
            $table->json('presets')->nullable();

            $table->timestamps();

            $table->unique(['theme_version_id', 'handle']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_sections');
    }
};
