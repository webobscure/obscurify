<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A theme "product" a store owns — the actual editable content
     * (templates/sections/blocks/settings) lives on its ThemeVersion
     * rows, never here. See docs/architecture/themes.md.
     */
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->string('status')->default('draft');

            $table->timestamps();

            $table->unique(['store_id', 'slug']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
