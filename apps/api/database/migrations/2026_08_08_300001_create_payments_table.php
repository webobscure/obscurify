<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `external_payment_id` is unique per (provider, external_payment_id)
     * across ALL stores, not scoped by store_id — webhook processing must
     * be able to look up the owning Payment/store BEFORE any TenantContext
     * is active (see ProcessPaymentWebhook), so this index deliberately
     * lives outside tenant scoping.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();

            $table->string('provider');
            $table->string('status')->default('pending');

            $table->char('currency', 3);
            $table->bigInteger('amount');
            $table->bigInteger('authorized_amount')->default(0);
            $table->bigInteger('captured_amount')->default(0);
            $table->bigInteger('refunded_amount')->default(0);

            $table->string('external_payment_id')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index('order_id');
            $table->unique(['provider', 'external_payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
