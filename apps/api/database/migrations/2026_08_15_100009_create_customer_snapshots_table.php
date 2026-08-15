<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only, one row per customer per capture — the historical
     * counterpart of customer_metrics (spec section 7: "Store calculated
     * metrics separately. Background recalculation."). Powers the admin's
     * "Customer Timeline" trend view (spec section 12). `metrics` is a
     * verbatim JSON copy of the CustomerMetric row's computed fields at
     * `captured_at` — a JSON blob rather than duplicating every column a
     * second time, since nothing here is ever queried by individual
     * metric value, only rendered as a time series.
     */
    public function up(): void
    {
        Schema::create('customer_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->json('metrics');
            $table->timestamp('captured_at');

            $table->index('store_id');
            $table->index(['customer_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_snapshots');
    }
};
