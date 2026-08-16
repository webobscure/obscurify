<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec section 11: "Historical orders must not change if merchant legal
 * profile changes later." Written once, at order completion, from
 * whatever StoreLegalProfile exists at that moment — never read live
 * from StoreLegalProfile afterward. Mirrors OrderItem's own
 * product_title/sku snapshot discipline, applied to the seller's legal
 * identity instead of the purchased items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_fiscal_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();

            $table->string('seller_legal_entity_type');
            $table->string('seller_legal_name');
            $table->string('seller_inn');
            $table->string('seller_kpp')->nullable();

            $table->string('vat_rate')->default('none');
            $table->bigInteger('vat_amount')->default(0);

            $table->boolean('receipt_required')->default(false);

            $table->timestamps();

            $table->unique('order_id');
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_fiscal_snapshots');
    }
};
