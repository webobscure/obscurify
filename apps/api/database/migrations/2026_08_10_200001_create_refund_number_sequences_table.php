<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One counter row per store — mirrors order_number_sequences/
     * return_number_sequences exactly (AllocateRefundNumber locks this
     * row inside the same transaction as Refund creation).
     */
    public function up(): void
    {
        Schema::create('refund_number_sequences', function (Blueprint $table) {
            $table->foreignUlid('store_id')->primary()->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('next_number')->default(1001);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_number_sequences');
    }
};
