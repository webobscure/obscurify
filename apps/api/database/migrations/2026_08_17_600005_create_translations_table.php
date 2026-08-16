<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A queryable INDEX over the real runtime translation sources
 * (Laravel's own `lang/{locale}/*.php` files for the backend, the
 * frontend's per-locale JSON bundles for Admin/Storefront UI copy) —
 * NOT itself the source Laravel/Vue I18n read at request/render time
 * (see docs/architecture/localization.md "Decision 1"). Populated by
 * `php artisan translations:scan`, which walks both file sources and
 * upserts one row per (key, locale) it finds — this is what powers
 * "detect missing translations, detect unused keys" (spec section 16)
 * without re-parsing files on every admin request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('translation_key_id')->constrained('translation_keys')->cascadeOnDelete();
            $table->string('locale_code');
            $table->foreign('locale_code')->references('code')->on('locales')->cascadeOnDelete();
            $table->text('value');
            $table->string('source')->default('scan');

            $table->timestamps();

            $table->unique(['translation_key_id', 'locale_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
