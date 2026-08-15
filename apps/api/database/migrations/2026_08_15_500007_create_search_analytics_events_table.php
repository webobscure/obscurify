<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Downstream-of-a-search events (spec section 12): a result click,
     * a zero-result search flagged for follow-up, or a search-attributed
     * order conversion. `search_query_id` links back to the SearchQuery
     * the event followed — see docs/architecture/search.md §9 for how
     * this feeds Search Analytics / CTR / conversion.
     */
    public function up(): void
    {
        Schema::create('search_analytics_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('search_query_id')->nullable()->constrained('search_queries')->nullOnDelete();
            $table->string('event_type');
            $table->foreignUlid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('position')->nullable();
            $table->foreignUlid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->index('store_id');
            $table->index(['store_id', 'event_type']);
            $table->index(['store_id', 'search_query_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_analytics_events');
    }
};
