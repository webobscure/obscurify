<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per store — active fiscalization provider, default VAT rate
 * applied to a new ProductFiscalProfile (spec section 17's "Tax / VAT
 * Settings" page), and whether a receipt is required at all before a
 * store has configured its legal profile / has no reason to fiscalize
 * (e.g. a B2B-only store using bank transfer with paper invoices).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscalization_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('active_provider_id')->nullable()->constrained('fiscalization_providers')->nullOnDelete();
            $table->string('default_vat_rate')->default('none');
            $table->boolean('receipts_required')->default(false);
            $table->timestamps();

            $table->unique('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscalization_settings');
    }
};
