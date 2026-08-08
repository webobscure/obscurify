<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `store_id` is nullable and deliberately NOT a forced/auto-injected
     * tenant column (this model does not use BelongsToTenant): a webhook
     * arrives with no TenantContext at all — the whole point of this
     * table is to let us claim-and-deduplicate the delivery (via the
     * (provider, external_event_id) unique index below) BEFORE we know
     * which store it belongs to. store_id is backfilled once the owning
     * Payment is resolved; it stays null for an event that could never be
     * matched to a Payment at all (see ProcessPaymentWebhook).
     *
     * The (provider, external_event_id) unique constraint is the actual
     * concurrency/idempotency primitive for webhook processing — same
     * claim/poll pattern as idempotency_keys (see IdempotencyKeyStore),
     * necessarily reimplemented here rather than reusing that table
     * directly, since idempotency_keys is itself tenant-scoped and can't
     * be written to before a tenant is known.
     */
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();

            $table->string('provider');
            $table->string('external_event_id');
            $table->string('external_payment_id')->nullable();

            $table->string('event_type');

            $table->string('payload_hash');
            $table->timestamp('processed_at')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['provider', 'external_event_id']);
            $table->index('store_id');
            $table->index('external_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
