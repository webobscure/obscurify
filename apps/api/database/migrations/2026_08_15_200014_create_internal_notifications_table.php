<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs the "Create internal notification" automation action (spec
     * section 5) — no notification system exists anywhere else in this
     * codebase yet (confirmed by research before this migration was
     * written), so this is a minimal admin-facing inbox row, not a
     * wrapper around Laravel's Notification facade (no mail/SMS/push
     * channel exists to route through).
     */
    public function up(): void
    {
        Schema::create('internal_notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('level')->default('info');
            $table->string('related_type')->nullable();
            $table->ulid('related_id')->nullable();
            $table->foreignUlid('workflow_execution_id')->nullable()->constrained('workflow_executions')->nullOnDelete();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index(['store_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_notifications');
    }
};
