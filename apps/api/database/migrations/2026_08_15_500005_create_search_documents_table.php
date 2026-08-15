<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The indexed, denormalized representation of one product — the
     * platform (and DatabaseSearchProvider) reads only this table at
     * search time, never Product/ProductVariant/InventoryLevel directly
     * (spec section 3: "Never search directly from Product models").
     * `search_text` is the accent-stripped, lowercased concatenation of
     * every textual field (see SearchTextNormalizer) — matched against
     * directly rather than re-normalizing Product data on every query.
     * `collection_ids`/`category_ids`/`tags` are denormalized id/label
     * arrays for fast facet counting without a join; the human-readable
     * facet *labels* for collections/categories are still resolved live
     * from their own tables at facet-response time (see
     * docs/architecture/search.md §3) so a renamed collection never
     * requires a reindex.
     */
    public function up(): void
    {
        Schema::create('search_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('vendor')->nullable();
            $table->string('product_type')->nullable();
            // jsonb, not json: DatabaseSearchProvider's facet counting and
            // array-overlap filtering need jsonb's own operators
            // (jsonb_array_elements_text(), ?|) — plain json has neither.
            $table->jsonb('tags')->nullable();
            $table->jsonb('collection_ids')->nullable();
            $table->jsonb('category_ids')->nullable();
            $table->jsonb('variant_option_values')->nullable();
            $table->bigInteger('price_min')->nullable();
            $table->bigInteger('price_max')->nullable();
            $table->string('currency')->nullable();
            $table->boolean('availability')->default(false);
            $table->integer('inventory_quantity')->default(0);
            $table->string('status');
            $table->boolean('is_searchable')->default(false);
            $table->string('thumbnail_url')->nullable();
            $table->integer('popularity')->default(0);
            $table->integer('sales_count')->default(0);
            $table->integer('search_score')->default(0);
            $table->text('search_text');
            $table->timestamp('product_created_at');
            $table->timestamp('product_updated_at');

            $table->timestamps();

            $table->index('store_id');
            $table->unique(['store_id', 'product_id']);
            $table->index(['store_id', 'is_searchable']);
            $table->index(['store_id', 'is_searchable', 'price_min']);
            $table->index(['store_id', 'is_searchable', 'sales_count']);
            $table->index(['store_id', 'is_searchable', 'product_created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_documents');
    }
};
