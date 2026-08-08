<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_products', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['collection_id', 'product_id']);
            $table->index('store_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_products');
    }
};
