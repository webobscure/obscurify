<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Access and refresh tokens both live here (`type`), distinguished
     * by TTL — access tokens are short-lived, refresh tokens are
     * long-lived and single-use (rotated on every refresh; `rotated_from_id`
     * chains a token to the one it replaced, so a reused/stolen refresh
     * token — one whose `rotated_from_id` chain shows it's already been
     * superseded — is detectable). `token_hash` is a SHA-256 hash; the
     * plaintext value is returned exactly once, at issuance, never
     * persisted (spec section 5: "Never store plaintext secrets"). The
     * self-referencing FK is added in a second Schema::table() step
     * (Postgres can't validate a self-referencing FK against a
     * not-yet-committed primary key inside the same Schema::create()).
     */
    public function up(): void
    {
        Schema::create('app_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('installed_app_id')->constrained('installed_apps')->cascadeOnDelete();
            $table->ulid('rotated_from_id')->nullable();

            $table->string('type');
            $table->string('token_hash')->unique();
            $table->json('scope');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('store_id');
            $table->index(['installed_app_id', 'type']);
        });

        Schema::table('app_tokens', function (Blueprint $table) {
            $table->foreign('rotated_from_id')->references('id')->on('app_tokens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_tokens');
    }
};
