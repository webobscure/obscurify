<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reference Provider Hardening: OrderShippingLine needs the same
     * metadata carry-through ShippingQuote already has (weight, pickup
     * point snapshot, etc.) — it was missed when OrderShippingLine was
     * first created (Shipping Foundation), since nothing needed it yet.
     * CompleteCheckout copies ShippingQuote.metadata here verbatim, same
     * snapshot-at-order-time reasoning as every other OrderShippingLine
     * column.
     */
    public function up(): void
    {
        Schema::table('order_shipping_lines', function (Blueprint $table) {
            $table->jsonb('metadata')->nullable()->after('estimated_days_max');
        });
    }

    public function down(): void
    {
        Schema::table('order_shipping_lines', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
