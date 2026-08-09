<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deliberately simple destination matching (spec section 3) — not a
     * worldwide tax/address engine. A region row matches a destination
     * when country_code matches exactly, region is either null (matches
     * any region in that country) or matches exactly, and
     * postal_code_pattern is either null or matches as a simple prefix
     * (e.g. "101" matches "101000"..."101999") — see
     * MatchesShippingDestination.
     */
    public function up(): void
    {
        Schema::create('shipping_zone_regions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();

            $table->string('country_code', 2);
            $table->string('region')->nullable();
            $table->string('postal_code_pattern')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index(['shipping_zone_id', 'country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_regions');
    }
};
