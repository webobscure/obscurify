<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per store — the single pointer ThemeRenderer resolves
     * first (spec section 2: "Only one theme may be active"). Points at
     * a specific *version*, not just a theme, so "rollback" is simply
     * repointing `theme_version_id` at an older published version.
     */
    public function up(): void
    {
        Schema::create('active_themes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->unique()->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->foreignUlid('theme_version_id')->constrained('theme_versions')->cascadeOnDelete();

            $table->timestamp('activated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_themes');
    }
};
