<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `locale` is nullable and unused by SynonymExpander's matching
     * logic this milestone (every synonym row applies regardless of
     * query locale) — present now so a future multilingual pass adds a
     * WHERE clause, not a migration (spec section 9: "Architecture must
     * support multilingual synonyms later").
     */
    public function up(): void
    {
        Schema::create('search_synonyms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('term');
            $table->json('synonyms');
            $table->boolean('is_bidirectional')->default(false);
            $table->string('locale')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('store_id');
            $table->index(['store_id', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_synonyms');
    }
};
