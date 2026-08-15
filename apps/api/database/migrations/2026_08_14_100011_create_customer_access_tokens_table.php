<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mirrors app_tokens exactly (see AppToken/IssueAppTokenPair) —
        // hashed bearer tokens, access+refresh pair, rotation chained via
        // rotated_from_id. The proven shape from the Apps OAuth token
        // system, reused here for the same reason: short-lived access
        // token, long-lived single-use-per-rotation refresh token, reuse
        // of a spent refresh token is a theft signal.
        Schema::create('customer_access_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUlid('customer_session_id')->constrained('customer_sessions')->cascadeOnDelete();
            $table->ulid('rotated_from_id')->nullable();
            $table->string('type');
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at');

            $table->index('store_id');
            $table->index('customer_id');
            $table->index('customer_session_id');
        });

        // Self-referencing FK: Postgres can't validate it against a
        // not-yet-committed primary key inside the same Schema::create()
        // (see app_tokens migration for the identical precedent).
        Schema::table('customer_access_tokens', function (Blueprint $table) {
            $table->foreign('rotated_from_id')->references('id')->on('customer_access_tokens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_access_tokens');
    }
};
