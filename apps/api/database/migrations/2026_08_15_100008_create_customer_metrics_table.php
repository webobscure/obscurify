<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per customer — the *current* computed values, upserted
     * incrementally by RecomputeCustomerMetrics whenever a triggering
     * event happens (spec section 8), and periodically recomputed from
     * scratch by the background command (spec section 7) to correct any
     * incremental drift. CustomerSnapshot (next migration) is the
     * append-only historical counterpart of this table.
     *
     * Money columns are minor units (ADR-010), same convention as every
     * other money column in this codebase.
     */
    public function up(): void
    {
        Schema::create('customer_metrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->unsignedBigInteger('total_spent_amount')->default(0);
            $table->unsignedBigInteger('average_order_value_amount')->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->unsignedInteger('refund_count')->default(0);
            $table->unsignedInteger('return_count')->default(0);
            // Stored as parts-per-10000 (basis points, i.e. 12.34% =
            // 1234) so it stays an integer like every other numeric
            // column here, rather than mixing a float into an otherwise
            // all-integer schema.
            $table->unsignedInteger('return_rate_bps')->default(0);
            $table->unsignedBigInteger('lifetime_value_amount')->default(0);
            $table->string('currency', 3)->nullable();

            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->timestamp('computed_at');

            $table->timestamps();

            $table->index('store_id');
            $table->unique('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_metrics');
    }
};
