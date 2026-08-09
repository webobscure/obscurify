<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 7 (spec section 12): a Shipment must reference the
     * Fulfillment it ships, and is no longer created directly from an
     * Order. Not nullable — every remaining creation path (CreateShipment)
     * now requires a Fulfillment. Any Shipment/ShipmentItem rows that
     * predate this migration were created via the old Order-direct path
     * this same milestone removes and cannot retroactively be given a
     * real Fulfillment, so they're cleared here — this table shipped in
     * the same release cycle as this migration (Shipping Foundation,
     * Milestone 6) and only ever held dev/E2E fixture data, never a real
     * order.
     */
    public function up(): void
    {
        DB::table('tracking_events')->delete();
        DB::table('shipment_items')->delete();
        DB::table('shipments')->delete();

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignUlid('fulfillment_id')->after('order_id')
                ->constrained('fulfillments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fulfillment_id');
        });
    }
};
