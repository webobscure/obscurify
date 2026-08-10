<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same type+parameters pattern as promotion_rules — see that
     * migration's docblock. A Promotion may have multiple actions (e.g. a
     * Buy X Get Y promotion pairs a 'product'/'order_quantity' rule with a
     * 'free_product' action). Same wholesale-replace-on-update safety
     * reasoning as promotion_rules: nothing references a specific action
     * row's id.
     */
    public function up(): void
    {
        Schema::create('promotion_actions', function (Blueprint $table) {
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
        Schema::dropIfExists('promotion_actions');
    }
};
