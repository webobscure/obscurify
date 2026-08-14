<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single-use authorization code grant (OAuth 2.1: Authorization
     * Code + PKCE only — no implicit flow). `code_hash` is a SHA-256
     * hash of the code (never stored plaintext, same discipline as
     * client secrets/tokens); `code_challenge`/`code_challenge_method`
     * are PKCE's own values, verified against the token exchange's
     * `code_verifier`. `used_at` enforces single-use: ExchangeAuthorizationCode
     * claims the row under a lock and rejects a replay.
     */
    public function up(): void
    {
        Schema::create('oauth_authorizations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('oauth_client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->foreignUlid('installed_app_id')->constrained('installed_apps')->cascadeOnDelete();

            $table->string('code_hash')->unique();
            $table->string('code_challenge');
            $table->string('code_challenge_method');
            $table->string('redirect_uri');
            $table->json('scope');

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('store_id');
            $table->index(['oauth_client_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_authorizations');
    }
};
