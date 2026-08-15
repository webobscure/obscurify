<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (recipient, channel) delivery attempt chain — the
     * notification-domain sibling of WebhookDelivery/WorkflowExecution's
     * own attempt_count/next_retry_at retry bookkeeping (same
     * MAX_ATTEMPTS/backoff convention, see SendNotificationDeliveryJob).
     * `idempotency_key` is unique so a duplicated dispatch can never
     * create a second delivery row for the same (notification,
     * recipient) pair.
     */
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->foreignUlid('recipient_id')->constrained('notification_recipients')->cascadeOnDelete();
            $table->string('channel');
            $table->foreignUlid('provider_id')->nullable()->constrained('notification_providers')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('response_meta')->nullable();
            $table->string('error_message')->nullable();
            $table->string('idempotency_key');

            $table->timestamps();

            $table->index('store_id');
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'status', 'next_retry_at']);
            $table->unique('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
