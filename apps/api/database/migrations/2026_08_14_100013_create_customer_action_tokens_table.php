<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-use, hashed, expiring tokens backing both password reset
        // and email verification (spec section 2) — one table with a
        // `purpose` column rather than two near-identical tables, the
        // same simplification Laravel's own password_reset_tokens makes
        // versus a bespoke table per feature. Not one of the milestone's
        // "core entities" (Customer/CustomerIdentity/.../CustomerEvent) —
        // this is auth plumbing underneath CustomerIdentity, not a new
        // domain concept.
        Schema::create('customer_action_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('purpose');
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at');

            $table->index('store_id');
            $table->index(['customer_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_action_tokens');
    }
};
