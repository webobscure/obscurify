<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();

            $table->string('provider');
            $table->string('external_shipment_id')->nullable();

            $table->string('status')->default('pending');

            $table->string('tracking_number')->nullable();
            $table->string('tracking_url')->nullable();

            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            // Matches payments' (provider, external_payment_id) uniqueness
            // rationale: this is what a webhook resolves tenant/identity
            // from — see ProcessShippingWebhook. Nullable because it's only
            // assigned once the provider confirms creation.
            $table->unique(['provider', 'external_shipment_id']);
            $table->index('store_id');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
