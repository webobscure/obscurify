<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A merchant-owned (or, from Milestone 12 on, app-owned via
     * `owner_type`/`owner_id`) subscription to Platform Events — see
     * docs/architecture/webhooks.md. `event_types` is a jsonb array of
     * event_type strings, or `["*"]` to subscribe to everything;
     * `secret` is the HMAC signing key, always encrypted at rest (see
     * WebhookSubscription::casts()) and never returned by the API after
     * creation.
     */
    public function up(): void
    {
        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('owner_type')->default('store');
            $table->ulid('owner_id')->nullable();

            $table->string('name');
            $table->string('target_url');
            $table->text('secret');
            $table->json('event_types');
            $table->string('status')->default('active');

            $table->timestamps();

            $table->index('store_id');
            $table->index(['store_id', 'status']);
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
    }
};
