<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * refunded quantity <= (ReturnItem.quantity - already-refunded
     * quantity for that ReturnItem) is enforced in RequestRefund under a
     * row lock, not a DB CHECK constraint — same discipline as every
     * other quantity ceiling in this codebase (fulfillment_items,
     * shipment_items, return_items).
     */
    public function up(): void
    {
        Schema::create('refund_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('refund_id')->constrained('refunds')->cascadeOnDelete();
            $table->foreignUlid('return_item_id')->constrained('return_items')->cascadeOnDelete();

            $table->unsignedInteger('quantity');
            $table->bigInteger('amount');

            $table->timestamps();

            $table->index('store_id');
            $table->index('refund_id');
            $table->index('return_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_items');
    }
};
