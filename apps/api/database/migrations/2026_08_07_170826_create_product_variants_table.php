<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('title');
            $table->string('sku')->nullable();
            $table->bigInteger('price_amount');
            $table->char('currency', 3);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            // Postgres treats NULLs as distinct in a unique index, so
            // multiple variants with no SKU remain valid.
            $table->unique(['store_id', 'sku']);
            $table->index('store_id');
            $table->index('product_id');
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
