<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A named navigation menu (e.g. "Main menu", "Footer") — `handle` is
     * the stable identifier a theme references to render a specific menu
     * (e.g. a theme's header section settings point at a handle, not a
     * ULID, so duplicating/rolling back a theme never breaks the
     * reference). Items live on MenuItem, self-nesting for submenus.
     */
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('name');
            $table->string('handle');

            $table->timestamps();

            $table->unique(['store_id', 'handle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
