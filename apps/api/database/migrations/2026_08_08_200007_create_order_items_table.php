<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();

            // nullOnDelete, not cascade: deleting/renaming the live Product
            // or Variant later must never touch this historical snapshot.
            $table->foreignUlid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignUlid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->string('product_title');
            $table->string('variant_title')->nullable();
            $table->string('sku')->nullable();

            $table->bigInteger('unit_price_amount');
            $table->unsignedInteger('quantity');
            $table->bigInteger('line_total_amount');
            $table->char('currency', 3);

            // Immutable snapshot row — created_at only, matches
            // inventory_movements' pattern for append-only history.
            $table->timestamp('created_at')->useCurrent();

            $table->index('store_id');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
