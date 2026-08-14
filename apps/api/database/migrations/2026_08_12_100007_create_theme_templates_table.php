<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per fixed template slot per version (Home, Collection,
     * Product, Cart, Checkout, Search, Blog, 404 — spec section 4).
     * `sections` is the ordered list of section *instances* placed on
     * this template — `[{id, section_handle, settings, blocks: [{id,
     * block_handle, settings}]}]` — referencing ThemeSection/ThemeBlock
     * *type* rows by handle for their schema, never embedding the
     * schema itself. This is the one place instance-level content
     * (what a merchant actually configured) lives, matching Shopify's
     * own `templates/*.json` shape (spec section 8).
     */
    public function up(): void
    {
        Schema::create('theme_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('theme_version_id')->constrained('theme_versions')->cascadeOnDelete();

            $table->string('type');
            $table->string('name');
            $table->json('sections');

            $table->timestamps();

            $table->unique(['theme_version_id', 'type']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_templates');
    }
};
