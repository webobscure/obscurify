<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per attempt to initiate a provider payment (i.e. per
     * createPayment() call) — never mutated into a different attempt,
     * a retried initiation gets its own row. Deliberately holds no
     * secrets/credentials, only safe provider-facing metadata.
     */
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignUlid('payment_session_id')->nullable()->constrained('payment_sessions')->nullOnDelete();

            $table->string('provider');
            $table->string('status');

            $table->string('external_attempt_id')->nullable();

            $table->string('error_code')->nullable();
            $table->string('error_message')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
