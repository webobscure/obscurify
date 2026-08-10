<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only financial timeline (spec section 16) — mirrors
     * return_events/fulfillment_events exactly. Scoped to `order_id`, not
     * to one Payment or Refund: spec's own example events ("Payment
     * captured", "Refund requested", "Refund completed", "Ledger
     * created") span both, so this is one unified per-order financial
     * timeline rather than a per-Payment/per-Refund one.
     */
    public function up(): void
    {
        Schema::create('financial_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();

            $table->string('type');
            $table->text('description')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamp('created_at');

            $table->index('store_id');
            $table->index(['order_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_events');
    }
};
