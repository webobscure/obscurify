<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Multiple fulfillments may exist per Order (partial fulfillment) —
     * order_id is deliberately not unique. See docs/architecture/
     * fulfillment.md for the full lifecycle.
     */
    public function up(): void
    {
        Schema::create('fulfillments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();

            $table->string('status')->default('pending');

            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillments');
    }
};
