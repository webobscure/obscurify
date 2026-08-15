<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (store, metric, day) — the only granularity ever
     * persisted. Every coarser time dimension (last 7/30 days, month,
     * quarter, year, custom range — spec section 4) is computed by
     * summing daily rows within the requested range at read time (an
     * indexed range query + SUM, not a transactional-table scan) —
     * see docs/architecture/analytics.md §4.
     *
     * `value`/`count` back scalar metrics (`count` lets
     * MetricCalculator derive averages like AOV without re-deriving
     * from raw events); `breakdown` backs leaderboard metrics (Top
     * Products/Categories/Collections/Discounts/Shipping Methods —
     * spec section 3) as a {entity_key: {label, value}} map.
     */
    public function up(): void
    {
        Schema::create('analytics_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('metric_key');
            $table->date('period_date');

            $table->bigInteger('value')->nullable();
            $table->unsignedInteger('count')->nullable();
            $table->json('breakdown')->nullable();

            $table->timestamp('computed_at');
            $table->timestamps();

            $table->unique(['store_id', 'metric_key', 'period_date']);
            $table->index(['store_id', 'metric_key', 'period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_snapshots');
    }
};
