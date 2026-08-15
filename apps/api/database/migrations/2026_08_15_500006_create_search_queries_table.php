<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_queries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('query_text');
            $table->string('normalized_query');
            $table->json('filters')->nullable();
            $table->string('sort')->nullable();
            $table->unsignedInteger('result_count');
            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('session_id')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index(['store_id', 'normalized_query']);
            $table->index(['store_id', 'result_count']);
            $table->index(['store_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_queries');
    }
};
