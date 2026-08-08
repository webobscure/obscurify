<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One counter row per store — AllocateOrderNumber locks this row
     * (SELECT ... FOR UPDATE) inside the same transaction as Order
     * creation, so concurrent checkouts in the same store still get
     * gap-free, unique, increasing numbers without a global DB sequence
     * (which can't be reset/scoped per tenant) or racy MAX(number)+1.
     */
    public function up(): void
    {
        Schema::create('order_number_sequences', function (Blueprint $table) {
            $table->foreignUlid('store_id')->primary()->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('next_number')->default(1001);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_number_sequences');
    }
};
