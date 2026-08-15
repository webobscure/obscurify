<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One run of a report — either ad hoc (`saved_report_id` null) or
     * generated from a `SavedReport` config. `result` is the computed
     * row set at the time of generation (a snapshot, not a live query),
     * consistent with spec section 12: reports are never calculated
     * synchronously from transactional tables at *read* time — the
     * (possibly synchronous, but bounded and against Analytics' own
     * tables only) calculation happens once, here, at generation time.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('saved_report_id')->nullable()->constrained('saved_reports')->nullOnDelete();

            $table->string('report_type');
            $table->json('filters')->default('{}');
            $table->json('columns')->default('[]');

            // pending | completed | failed
            $table->string('status')->default('pending');
            $table->json('result')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('store_id');
            $table->index(['store_id', 'report_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
