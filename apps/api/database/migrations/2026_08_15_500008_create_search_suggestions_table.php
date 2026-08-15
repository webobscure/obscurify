<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A materialized, refreshed-not-live cache of autocomplete
     * candidates (spec section 8: "Popular queries") — deliberately not
     * computed by aggregating SearchQuery on every autocomplete
     * keystroke (spec section 16: "No table scans on every request").
     * See RefreshSearchSuggestionsCommand.
     */
    public function up(): void
    {
        Schema::create('search_suggestions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('term');
            $table->string('type');
            $table->ulid('reference_id')->nullable();
            $table->integer('score')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('store_id');
            $table->index(['store_id', 'type', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_suggestions');
    }
};
