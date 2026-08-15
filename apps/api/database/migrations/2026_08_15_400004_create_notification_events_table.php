<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The routing table between a Platform Event and a template + channel
     * (spec section 7: "Notifications may originate from Platform
     * Events") — the notification-domain sibling of WebhookSubscription
     * (event_type -> target_url) and WorkflowTrigger (event_type ->
     * workflow), matched by DispatchNotificationsForEvent.
     */
    public function up(): void
    {
        Schema::create('notification_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('event_type');
            $table->string('channel');
            $table->foreignUlid('template_id')->constrained('notification_templates')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();

            $table->index('store_id');
            $table->index(['store_id', 'event_type']);
            $table->unique(['store_id', 'event_type', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_events');
    }
};
