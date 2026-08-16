<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One legal profile per store (spec section 1) — deliberately its own
 * table, not columns on `stores`: legal identity is a distinct concern
 * from the storefront-facing Store row (name/slug/currency/locale), has
 * its own validation rules per legal_entity_type, and every historical
 * Order snapshots this data at completion time (see
 * order_fiscal_snapshots) rather than reading it live, so a store can
 * safely edit its legal details without rewriting past orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_legal_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('legal_entity_type');

            $table->string('legal_name');
            $table->string('short_name')->nullable();

            $table->string('inn');
            $table->string('kpp')->nullable();
            $table->string('ogrn')->nullable();
            $table->string('ogrnip')->nullable();

            $table->jsonb('legal_address')->nullable();
            $table->jsonb('actual_address')->nullable();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->timestamps();

            $table->unique('store_id');
            $table->index('inn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_legal_profiles');
    }
};
