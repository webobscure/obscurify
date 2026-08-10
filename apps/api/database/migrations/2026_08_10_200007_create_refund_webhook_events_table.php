<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors payment_webhook_events exactly, same reasoning transplanted
     * verbatim: `store_id` is nullable and this model does not use
     * BelongsToTenant — a webhook arrives with no TenantContext at all,
     * so the (provider, external_event_id) unique index below is what
     * claims/deduplicates a delivery BEFORE we know which store it
     * belongs to (see ProcessRefundWebhook). Kept as its own table rather
     * than reusing payment_webhook_events, so Financial's own webhook
     * idempotency never requires Financial to write into a table owned
     * by the Payments domain.
     */
    public function up(): void
    {
        Schema::create('refund_webhook_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();

            $table->string('provider');
            $table->string('external_event_id');
            $table->string('external_refund_id')->nullable();

            $table->string('event_type');

            $table->string('payload_hash');
            $table->timestamp('processed_at')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['provider', 'external_event_id']);
            $table->index('store_id');
            $table->index('external_refund_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_webhook_events');
    }
};
