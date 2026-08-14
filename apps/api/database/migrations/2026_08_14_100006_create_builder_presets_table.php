<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A named, ready-to-insert starting configuration for a section or
     * block type — the Section Library / Block Library (spec sections
     * 5/6) the visual Builder's "add section"/"add block" picker reads
     * from. `type` discriminates section vs. block presets in one table
     * rather than two near-identical ones. `handle` matches a
     * ThemeSection.handle or ThemeBlock.handle the active theme defines
     * (see BuiltInLibrary — the built-in catalog this table is seeded
     * from). `settings` is copied into a new SectionInstance/
     * BlockInstance once, at insert time, never referenced afterward —
     * the same "preset is a plain copy, not a live reference" principle
     * PageTemplate already established (ADR-020).
     */
    public function up(): void
    {
        Schema::create('builder_presets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('type');
            $table->string('handle');
            $table->string('name');
            $table->json('settings');

            $table->timestamps();

            $table->unique(['store_id', 'type', 'handle', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_presets');
    }
};
