<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An immutable snapshot once published (spec section 12: "Support
     * immutable theme versions... publish v1, v2, v3... rollback must be
     * possible"). Exactly one `draft` version per theme is the live
     * working copy (edited in place via ThemeTemplate/ThemeSection/
     * ThemeBlock/ThemeSetting rows scoped to it); publishing freezes it
     * (status -> published, published_at set) and creates a fresh draft
     * cloned from it. `created_from_version_id` records lineage for
     * both "publish creates a new draft" and "duplicate" — added in a
     * second Schema::table() step, same reason as app_tokens'
     * self-referencing FK.
     */
    public function up(): void
    {
        Schema::create('theme_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->ulid('created_from_version_id')->nullable();

            $table->unsignedInteger('version_number');
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->unique(['theme_id', 'version_number']);
            $table->index('store_id');
            $table->index(['theme_id', 'status']);
        });

        Schema::table('theme_versions', function (Blueprint $table) {
            $table->foreign('created_from_version_id')->references('id')->on('theme_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_versions');
    }
};
