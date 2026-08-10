<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Multiple ReturnRequests may exist per Order — order_id is
     * deliberately not unique, same reasoning as fulfillments.order_id.
     * customer_id is nullable: a merchant-initiated return (no customer
     * account required yet, see docs/architecture/returns.md) still needs
     * a row here.
     */
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->unsignedBigInteger('number');
            $table->string('status')->default('requested');

            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index(['order_id', 'status']);
            $table->unique(['store_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
