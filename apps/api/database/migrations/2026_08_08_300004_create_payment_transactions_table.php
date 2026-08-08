<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable, append-only ledger — `created_at` only, matching the
     * OrderItem/OrderAddress snapshot convention (no updated_at column at
     * all). Every provider event/state change appends a new row; existing
     * rows are never updated. This is what "exactly one paid transition"
     * is verified against in the concurrency tests.
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('payment_id')->constrained('payments')->cascadeOnDelete();

            $table->string('type');
            $table->string('status');

            $table->char('currency', 3);
            $table->bigInteger('amount');

            $table->string('external_transaction_id')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('store_id');
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
