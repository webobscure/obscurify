<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * trigger_type separates *how* a promotion is activated (automatic vs
     * requires a DiscountCode) from *what it does* — the "Free Shipping /
     * Fixed Amount / Percentage / Buy X Get Y" types from the spec are
     * shapes of PromotionAction, not of Promotion itself (see
     * docs/architecture/promotions.md). stacking_mode + priority drive
     * PromotionEngine's conflict resolution: an eligible 'exclusive'
     * promotion always wins alone over anything else; 'stackable'
     * promotions combine, applied in priority order (lower first).
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_type');
            $table->string('stacking_mode')->default('stackable');
            $table->integer('priority')->default(0);
            $table->string('status')->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'trigger_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
