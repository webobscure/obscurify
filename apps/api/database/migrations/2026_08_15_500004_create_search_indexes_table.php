<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per store — tracks the index's own state (spec section 3:
     * "Support incremental indexing"), not search content itself. See
     * SearchDocument for the actual indexed rows.
     */
    public function up(): void
    {
        Schema::create('search_indexes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('status')->default('building');
            $table->unsignedInteger('document_count')->default(0);
            $table->timestamp('last_full_reindex_at')->nullable();
            $table->timestamp('last_indexed_at')->nullable();
            $table->string('error_message')->nullable();

            $table->timestamps();

            $table->unique('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_indexes');
    }
};
