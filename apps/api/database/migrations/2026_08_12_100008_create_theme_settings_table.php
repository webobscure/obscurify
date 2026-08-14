<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Global theme settings (Logo, Colors, Typography, Buttons,
     * Spacing, Radius, Container Width, Animations, Social Links,
     * Favicon — spec section 7), one row per key so a single setting
     * can be patched without rewriting a whole settings blob.
     */
    public function up(): void
    {
        Schema::create('theme_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('theme_version_id')->constrained('theme_versions')->cascadeOnDelete();

            $table->string('key');
            $table->json('value');

            $table->timestamps();

            $table->unique(['theme_version_id', 'key']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_settings');
    }
};
