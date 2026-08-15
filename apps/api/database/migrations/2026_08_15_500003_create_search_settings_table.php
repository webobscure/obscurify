<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('active_provider_id')->nullable()->constrained('search_providers')->nullOnDelete();
            $table->unsignedInteger('results_per_page')->default(24);
            $table->unsignedInteger('autocomplete_limit')->default(8);
            $table->boolean('typo_tolerance_enabled')->default(true);
            $table->boolean('synonyms_enabled')->default(true);
            $table->boolean('facets_enabled')->default(true);

            $table->timestamps();

            $table->unique('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_settings');
    }
};
