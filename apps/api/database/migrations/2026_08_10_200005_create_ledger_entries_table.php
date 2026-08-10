<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Double-entry style (spec section 5): every LedgerEntry is either a
     * `debit` or a `credit` against one `account` (a minimal two-account
     * chart — `cash`/`revenue`, see docs/architecture/financial.md — not
     * full GAAP; no AR/tax/COGS accounts this milestone). `amount` is
     * always a positive, unsigned figure — direction carries the sign,
     * not the amount itself, matching how a real ledger reads. Immutable:
     * created_at only, never updated.
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('ledger_transaction_id')->constrained('ledger_transactions')->cascadeOnDelete();

            $table->string('account');
            $table->string('direction');
            $table->char('currency', 3);
            $table->bigInteger('amount');

            $table->timestamp('created_at')->useCurrent();

            $table->index('store_id');
            $table->index('ledger_transaction_id');
            $table->index('account');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
