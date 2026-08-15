<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pinned_search_results', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('keyword');
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('store_id');
            $table->index(['store_id', 'keyword']);
            $table->unique(['store_id', 'keyword', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pinned_search_results');
    }
};
