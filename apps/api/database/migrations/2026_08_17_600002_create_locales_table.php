<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinct from `languages` deliberately (spec section 1 lists both as
 * separate core entities): a Language is "what natural language"; a
 * Locale is "what concrete runtime configuration" — 1:1 with its
 * Language today (ru/en/de have no region variants yet), but a future
 * `en-GB` alongside `en-US` is a new Locale row referencing the same
 * Language, not a schema change. `fallback_locale_code` is the chain
 * spec sections 5/7 ask for (e.g. `de` -> `en` -> platform default).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locales', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('language_code');
            $table->foreign('language_code')->references('code')->on('languages')->restrictOnDelete();
            $table->string('fallback_locale_code')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        // Self-referencing FK added after create() — same reasoning as
        // fiscal_receipts.correction_of_id (see that migration's own
        // docblock): Postgres can't resolve a FK against a table still
        // mid-creation in the same statement.
        Schema::table('locales', function (Blueprint $table) {
            $table->foreign('fallback_locale_code')->references('code')->on('locales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locales');
    }
};
