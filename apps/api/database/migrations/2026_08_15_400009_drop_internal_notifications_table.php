<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 21 (Notification Center) replaces the "Create internal
     * notification" automation action with real notification actions
     * (spec section 8: "Replace the minimal InternalNotification action
     * with real notification actions"). `internal_notifications` had no
     * read API anywhere (confirmed before writing this migration — it
     * was a write-only side effect of one workflow action) and no data
     * worth preserving, so it is dropped rather than kept alongside the
     * new `notifications` table as dead, unreachable rows. `in_app`-
     * channel Notifications are its direct successor.
     */
    public function up(): void
    {
        Schema::dropIfExists('internal_notifications');
    }

    public function down(): void
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
};
