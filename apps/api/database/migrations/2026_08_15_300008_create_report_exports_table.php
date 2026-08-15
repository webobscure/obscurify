<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec section 9: CSV/Excel/PDF export, "scheduled export
     * architecture only" — `scheduled_at`/`recurrence` are stored so
     * the data model genuinely supports scheduling, but nothing in
     * this milestone wires a scheduler to execute them, matching this
     * codebase's standing convention (outbox:process,
     * webhooks:retry-failed, automation:resume-delayed, ... are all
     * "run externally on a cron," never wired into Laravel's own
     * scheduler). An export with `scheduled_at` null is an immediate,
     * synchronous "export now" — see ExportReport.
     */
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('report_id')->constrained('reports')->cascadeOnDelete();

            // csv | xlsx | pdf
            $table->string('format');
            // pending | completed | failed
            $table->string('status')->default('pending');
            $table->string('file_path')->nullable();
            $table->unsignedInteger('file_size')->nullable();

            $table->timestamp('scheduled_at')->nullable();
            // daily | weekly | monthly, null = one-off
            $table->string('recurrence')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
