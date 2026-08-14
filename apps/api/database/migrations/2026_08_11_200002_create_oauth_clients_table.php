<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One OAuthClient per App (1:1) — created together at registration.
     * `client_secret_hash` is a SHA-256 hash; the plaintext secret is
     * returned exactly once, at creation (see AppController::store()),
     * the same "never store plaintext, show once" discipline
     * WebhookSubscription.secret already established.
     */
    public function up(): void
    {
        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('app_id')->unique()->constrained('apps')->cascadeOnDelete();

            $table->string('client_id')->unique();
            $table->string('client_secret_hash');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_clients');
    }
};
