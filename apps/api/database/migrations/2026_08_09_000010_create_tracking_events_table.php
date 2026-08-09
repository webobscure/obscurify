<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only history (spec section 21) — never updated or deleted
     * once written, one row per status transition/tracking update a
     * provider reports.
     */
    public function up(): void
    {
        Schema::create('tracking_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('shipment_id')->constrained('shipments')->cascadeOnDelete();

            $table->string('status');
            $table->string('description')->nullable();

            $table->timestamp('occurred_at');
            $table->string('location')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamp('created_at');

            $table->index('store_id');
            $table->index(['shipment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
    }
};
