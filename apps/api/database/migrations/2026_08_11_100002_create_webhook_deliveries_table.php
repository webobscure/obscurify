<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (webhook_subscription, outbox_event) pair — the unique
     * constraint below is the idempotency claim DispatchWebhooksForEvent
     * inserts against (mirrors ProcessShippingWebhook's insert-then-
     * catch-UniqueConstraintViolationException pattern for inbound
     * webhooks, applied to outbound fan-out instead). Mutates in place
     * across retries rather than growing an attempts log table — see
     * docs/architecture/webhooks.md for why that scope cut is fine here.
     */
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('webhook_subscription_id')->constrained('webhook_subscriptions')->cascadeOnDelete();
            $table->ulid('outbox_event_id');

            $table->string('event_type');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('response_code')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->unique(['webhook_subscription_id', 'outbox_event_id']);
            $table->index('store_id');
            $table->index(['status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
