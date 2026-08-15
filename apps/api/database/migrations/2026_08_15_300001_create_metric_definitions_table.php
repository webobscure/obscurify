<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The metric-picker catalog (spec section 3) — a global, platform-
     * wide table, deliberately not tenant-scoped, same reasoning as
     * Milestone 19's workflow_variables/workflow_templates
     * (BelongsToTenant's global scope always forces a non-null store_id,
     * which would hide shared built-in rows from every store). Seeded
     * once by RegisterBuiltInAnalyticsCatalog. `calculation` describes
     * how AnalyticsAggregator computes it (sum/count/average/derived/
     * leaderboard/gauge) — see docs/architecture/analytics.md §3.
     */
    public function up(): void
    {
        Schema::create('metric_definitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            // revenue | orders | customers | inventory | leaderboard
            $table->string('category');
            // currency | count | percentage | ratio
            $table->string('unit');
            // sum | count | average | derived | leaderboard | gauge | placeholder
            $table->string('calculation');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_definitions');
    }
};
