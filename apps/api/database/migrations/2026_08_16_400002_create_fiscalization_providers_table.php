<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors search_providers/notification_providers exactly (spec section
 * 13: "Use the same provider pattern as Payment/Shipping"). `credentials`
 * is a separate, always-encrypted column from `config` — see the model's
 * own docblock for why (spec section 20: "Encrypt provider credentials").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscalization_providers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('is_enabled')->default(true);
            $table->jsonb('config')->nullable();
            $table->text('credentials')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscalization_providers');
    }
};
