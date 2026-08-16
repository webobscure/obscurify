<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec section 8: "Extend payment capabilities for Russian methods."
 * `payment_method` is the customer-facing payment channel
 * (bank_card/sbp/bank_transfer/cash/credit) — distinct from `provider`
 * (which gateway processes it, e.g. "fake"/a future "yookassa").
 * Nullable: every payment created before this milestone (and every
 * non-Russian-market payment going forward) simply has no method
 * recorded, exactly like `tags` was added nullable to `products` in
 * Milestone 22. `method_metadata` holds the SBP/bank-transfer
 * preparation data (spec sections 9/10) — deliberately reusing the
 * `payments` table's own jsonb-column-per-concern convention rather
 * than yet another table for what is, today, inert preparation data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('provider');
            $table->jsonb('method_metadata')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'method_metadata']);
        });
    }
};
