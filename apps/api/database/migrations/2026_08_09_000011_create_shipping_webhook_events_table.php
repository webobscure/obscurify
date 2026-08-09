<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors payment_webhook_events exactly, same reasoning (see that
     * migration): a shipping webhook arrives with no TenantContext, so
     * this table's own (provider, external_event_id) unique index is what
     * claims/deduplicates a delivery before we even know which store it
     * belongs to — see ProcessShippingWebhook. store_id is filled in once
     * resolved, same as payments.
     *
     * Deliberately its own idempotency mechanism, not a third generic
     * system layered on top of IdempotencyKeyStore or payment_webhook_
     * events — this is the same shape of problem Payments already solved
     * (an unauthenticated, tenant-less webhook needs to dedupe before
     * tenant resolution is even possible), so it reuses that shape
     * directly rather than inventing a shared abstraction two call sites
     * don't yet justify.
     */
    public function up(): void
    {
        Schema::create('shipping_webhook_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();

            $table->string('provider');
            $table->string('external_event_id');
            $table->string('external_shipment_id')->nullable();
            $table->string('event_type');
            $table->string('payload_hash');

            $table->timestamp('processed_at')->nullable();

            $table->timestamp('created_at');

            $table->unique(['provider', 'external_event_id']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_webhook_events');
    }
};
