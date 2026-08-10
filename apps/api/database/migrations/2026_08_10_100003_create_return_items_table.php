<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * quantity <= (shipped quantity - already-returned quantity) for the
     * referenced OrderItem is enforced in RequestReturn under a row lock,
     * not as a DB CHECK constraint — same discipline as
     * fulfillment_items/shipment_items' own quantity ceilings. `condition`
     * here is the customer/merchant's own claim at request time — the
     * authoritative, verified condition lives on ReturnInspection.
     */
    public function up(): void
    {
        Schema::create('return_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('return_request_id')->constrained('return_requests')->cascadeOnDelete();
            $table->foreignUlid('order_item_id')->constrained('order_items')->cascadeOnDelete();

            $table->unsignedInteger('quantity');
            $table->string('reason');
            $table->string('condition')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index('return_request_id');
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};
