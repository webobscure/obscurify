<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `config` carries the widget's metric key(s), time-range choice,
     * and any display options — one jsonb bag rather than a column per
     * widget-type shape, since the six widget types (spec section 5)
     * each need a different config shape and none of them need to be
     * queried on individually.
     */
    public function up(): void
    {
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('dashboard_id')->constrained('dashboards')->cascadeOnDelete();

            // line_chart | bar_chart | pie_chart | metric_card | table | leaderboard
            $table->string('type');
            $table->string('title');
            $table->json('config');
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->index('store_id');
            $table->index(['dashboard_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};
