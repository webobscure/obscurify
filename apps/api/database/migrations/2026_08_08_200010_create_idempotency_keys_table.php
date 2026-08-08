<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The (store_id, operation, key) unique constraint is the actual
     * concurrency primitive — see IdempotencyKeyStore: the first of two
     * concurrent identical requests wins the INSERT, the second catches
     * the unique violation and blocks on a `SELECT ... FOR UPDATE` of the
     * winner's row until it commits, then replays its response.
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('operation');
            $table->string('key');
            $table->string('request_hash')->nullable();

            $table->unsignedSmallInteger('response_status')->nullable();
            $table->jsonb('response_body')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at');

            $table->unique(['store_id', 'operation', 'key']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
