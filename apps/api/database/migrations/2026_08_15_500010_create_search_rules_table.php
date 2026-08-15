<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Boost/hide merchandising rules (spec section 10) — `keyword` null
     * means the rule applies to every search, not just a matching one.
     * Pin rules are a separate, simpler entity (PinnedSearchResult) since
     * "always show this product first for this keyword" has a
     * meaningfully different shape (a position, not a score delta) than
     * boost/hide.
     */
    public function up(): void
    {
        Schema::create('search_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->string('keyword')->nullable();
            $table->string('action');
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('boost_amount')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('position')->default(0);

            $table->timestamps();

            $table->index('store_id');
            $table->index(['store_id', 'keyword']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_rules');
    }
};
