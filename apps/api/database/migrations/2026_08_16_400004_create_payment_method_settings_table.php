<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which RussianPaymentMethod values a store currently accepts (spec
 * section 17's "Payment Methods" admin page) — deliberately its own
 * small table rather than folded into fiscalization_settings, since
 * "which payment methods we accept" and "how we fiscalize" are
 * different merchant decisions that happen to share the Russian-market
 * scope of this milestone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_method_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->jsonb('enabled_methods')->default('[]');
            $table->timestamps();

            $table->unique('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_method_settings');
    }
};
