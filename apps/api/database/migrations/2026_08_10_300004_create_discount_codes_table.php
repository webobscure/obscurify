<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `code` is always stored normalized to uppercase (see DiscountCode
     * model) so lookup is case-insensitive with a plain unique index,
     * without a citext/lower() dependency. usage_count is a denormalized
     * counter incremented under a row lock (lockForUpdate) alongside a
     * PromotionUsage insert — the same locked-row pattern as
     * AllocateOrderNumber, just without a separate sequence table since
     * gap-free numbering isn't required here.
     */
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('promotion_id')->constrained('promotions')->cascadeOnDelete();

            $table->string('code');
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active');

            $table->timestamps();

            $table->unique(['store_id', 'code']);
            $table->index('promotion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_codes');
    }
};
