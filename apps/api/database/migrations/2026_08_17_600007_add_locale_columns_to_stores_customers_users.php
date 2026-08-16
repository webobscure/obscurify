<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec section 8: a store already has `default_locale` (existing
 * column, previously write-only — see Store's own docblock and
 * ADR-032) — this migration adds the other two: `admin_locale`
 * (Admin UI default for this store's admin users with no personal
 * preference) and `storefront_locale` (storefront default for an
 * anonymous visitor before Accept-Language/cookie negotiation).
 * `customers.locale`/`users.locale` are each person's own saved
 * preference, resolved before falling back to their store's
 * admin/storefront locale, then the platform default — see
 * ResolveRequestLocale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('admin_locale')->nullable()->after('default_locale');
            $table->string('storefront_locale')->nullable()->after('admin_locale');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('locale')->nullable()->after('phone');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('locale')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['admin_locale', 'storefront_locale']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
