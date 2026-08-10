<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Multiple Refunds may exist per Order (spec section 2) and per
     * Payment — order_id/payment_id are deliberately not unique.
     * `provider` is nullable: null means a manual refund (spec section
     * 11), no provider call was ever made. `shipping_amount` and
     * `adjustment_amount` are the non-itemized portions of `amount` —
     * shipping-only and manual-adjustment-only refunds (spec sections
     * 9/10) — the itemized portion lives in refund_items and must sum
     * with these two to equal `amount` (enforced in RequestRefund, not a
     * DB constraint, same discipline as every other quantity/amount
     * ceiling in this codebase).
     */
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('payment_id')->constrained('payments')->cascadeOnDelete();

            $table->unsignedBigInteger('number');
            $table->string('status')->default('requested');

            $table->char('currency', 3);
            $table->bigInteger('amount');
            $table->bigInteger('shipping_amount')->default(0);
            $table->bigInteger('adjustment_amount')->default(0);

            $table->text('reason')->nullable();

            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();

            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index(['order_id', 'status']);
            $table->index('payment_id');
            $table->unique(['store_id', 'number']);
            $table->unique(['provider', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
