<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A named bundle of ThemeSetting values a merchant can apply at once
     * (e.g. "Dark", "Spring") — scoped to the Theme (not one version),
     * since a look preset is conceptually theme-wide, not tied to a
     * single draft/published snapshot. Applying one bulk-upserts
     * ThemeSetting rows on the theme's current draft version.
     */
    public function up(): void
    {
        Schema::create('theme_presets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('theme_id')->constrained('themes')->cascadeOnDelete();

            $table->string('name');
            $table->json('settings');

            $table->timestamps();

            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_presets');
    }
};
