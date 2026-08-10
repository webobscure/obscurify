<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * type + parameters (jsonb, shape validated per-type in the FormRequest
     * — see StorePromotionRequest) is a fresh pattern for this codebase;
     * closest existing analog is PaymentTransaction.metadata, but that's
     * generic/untyped. All rules on a Promotion are AND-ed by RuleEngine —
     * no boolean grouping (see docs/architecture/promotions.md).
     *
     * Wholesale-replace-on-update (mirrors ShippingZoneRegion) is safe
     * here because nothing references a specific rule row's id —
     * PromotionUsage/DiscountApplication reference the Promotion, never a
     * PromotionRule.
     */
    public function up(): void
    {
        Schema::create('promotion_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('promotion_id')->constrained('promotions')->cascadeOnDelete();

            $table->string('type');
            $table->json('parameters');

            $table->timestamps();

            $table->index('store_id');
            $table->index('promotion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_rules');
    }
};
