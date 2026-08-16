<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec section 8's "Supported languages" — a many-to-many, unlike
 * default/admin/storefront language (each a single plain-string column
 * on `stores`, matching the existing `default_locale` convention).
 * Tenant-scoped via `store_id`; no BelongsToTenant trait needed since
 * this is always queried scoped to one store's row directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_supported_locales', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('locale_code');
            $table->foreign('locale_code')->references('code')->on('locales')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['store_id', 'locale_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_supported_locales');
    }
};
