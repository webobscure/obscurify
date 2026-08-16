<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec section 6: 54-FZ line-item fiscal attributes. `product_id`/
 * `product_variant_id` are informational back-references only (nullable,
 * no foreign key enforcement) — a receipt item is a fiscal snapshot in
 * its own right, same discipline as OrderItem, and must survive the
 * referenced product being deleted later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_receipt_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('fiscal_receipt_id')->constrained('fiscal_receipts')->cascadeOnDelete();
            $table->ulid('product_id')->nullable();
            $table->ulid('product_variant_id')->nullable();

            $table->string('name');
            $table->decimal('quantity', 12, 3);
            $table->bigInteger('price_amount');
            $table->bigInteger('amount');

            $table->string('vat_rate');
            $table->string('payment_method');
            $table->string('payment_subject');
            $table->string('unit_of_measure')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index('fiscal_receipt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_receipt_items');
    }
};
