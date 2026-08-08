<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The storefront-visible payment initiation attempt: what the browser
     * gets redirected to. Kept separate from Payment itself so a Payment
     * can, in principle, have more than one session (e.g. a retried
     * initiation) without losing the payment's own identity/history.
     */
    public function up(): void
    {
        Schema::create('payment_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('payment_id')->constrained('payments')->cascadeOnDelete();

            $table->string('provider');
            $table->string('status')->default('pending');

            $table->string('redirect_url')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index('order_id');
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_sessions');
    }
};
