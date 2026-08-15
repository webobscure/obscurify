<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exactly one trigger per version (spec section 3: triggers come
     * exclusively from the Platform Event Bus) — `event_type` is a plain
     * string matched against PlatformEventCatalog::knownEventTypes(),
     * the same "not an enforced enum" convention OutboxEvent.event_type
     * and WebhookSubscription already use, so a future event needs no
     * Automation-domain schema change to become triggerable (spec:
     * "Future events must register automatically").
     */
    public function up(): void
    {
        Schema::create('workflow_triggers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('workflow_version_id')->constrained('workflow_versions')->cascadeOnDelete();
            $table->string('event_type');
            $table->timestamps();

            $table->index('store_id');
            $table->unique('workflow_version_id');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_triggers');
    }
};
