<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opt-in per-item threshold — null means "no low-stock alerting for
     * this item." Backs the ProductBackInStock/InventoryBelowThreshold
     * triggers (spec section 3): AdjustInventory compares on-hand stock
     * across a crossing (0 -> positive, or above -> at-or-below this
     * value) and records the matching outbox event only when it actually
     * crosses, not on every adjustment.
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->unsignedInteger('low_stock_threshold')->nullable()->after('requires_shipping');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('low_stock_threshold');
        });
    }
};
