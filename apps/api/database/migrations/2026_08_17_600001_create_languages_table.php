<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide catalog, not tenant-scoped — the same "shared reference
 * data" status as a currency code, not a per-store configurable list
 * (spec section 2: "Allow adding more languages later. No hardcoded
 * language checks."). A store's own configuration (which of these it
 * supports, and its default/admin/storefront language) lives on
 * `stores`/`store_supported_locales` — see docs/architecture/localization.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('native_name');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
