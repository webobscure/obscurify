<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ShippingMethod <-> ShippingZone many-to-many (spec section 4). Both
     * sides are always the same store — store_id is carried here too
     * (rather than joined through) so BelongsToTenant can scope this pivot
     * exactly like every other tenant table, and so a cross-store pairing
     * can never be created even if a caller supplied a same-tenant-looking
     * but actually-foreign zone/method id pair.
     *
     * A plain auto-increment id, not a ULID (matching collection_products/
     * product_categories): this is an explicit pivot *model*
     * (ShippingMethodZone), created via ::query()->firstOrCreate() so
     * BelongsToTenant's creating() hook fills store_id — never through
     * Eloquent's sync()/attach(), which write the pivot table directly and
     * would bypass that hook entirely.
     */
    public function up(): void
    {
        Schema::create('shipping_method_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('shipping_method_id')->constrained('shipping_methods')->cascadeOnDelete();
            $table->foreignUlid('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['shipping_method_id', 'shipping_zone_id']);
            $table->index('store_id');
            $table->index('shipping_zone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_method_zones');
    }
};
