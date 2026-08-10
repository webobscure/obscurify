<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable accounting record — created_at only, no updated_at, no
     * delete path exposed anywhere (spec section 5: "History must never
     * be modified"). One LedgerTransaction always groups a balanced set
     * of LedgerEntry rows (sum(debits) == sum(credits) — enforced in
     * PostLedgerEntries, not a DB constraint, since Postgres can't express
     * a cross-row balance check declaratively without a trigger, and this
     * data is entirely internally generated, never user input).
     * `reference_type`/`reference_id` point at the Payment or Refund that
     * caused this transaction — polymorphic by convention (string class
     * name + ulid), the same pattern inventory_movements already uses,
     * not a real morphTo relation.
     */
    public function up(): void
    {
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();

            $table->string('reference_type');
            $table->ulid('reference_id');

            $table->text('description')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('store_id');
            $table->index('order_id');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
    }
};
