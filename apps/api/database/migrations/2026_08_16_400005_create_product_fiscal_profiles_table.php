<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec section 12: "Do not put Russian-specific fields directly
 * everywhere if a separate fiscal profile is cleaner" — VAT rate,
 * payment subject, and unit of measure live here instead of new columns
 * on `products`/`product_variants`. Polymorphic on
 * (fiscalizable_type, fiscalizable_id), the same shape `media` already
 * uses for Product/ProductVariant (see MediaEntityType) — a variant's
 * own profile overrides its product's for that one variant; a product
 * with no variant-level override falls back to its product-level
 * profile at receipt-item build time (see BuildFiscalReceiptItems).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_fiscal_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('fiscalizable_type');
            $table->ulid('fiscalizable_id');
            $table->string('vat_rate')->default('none');
            $table->string('payment_subject')->default('commodity');
            $table->string('unit_of_measure')->nullable();
            $table->timestamps();

            $table->unique(['fiscalizable_type', 'fiscalizable_id']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_fiscal_profiles');
    }
};
