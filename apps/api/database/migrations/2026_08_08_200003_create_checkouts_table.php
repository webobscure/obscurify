<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkouts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->char('currency', 3);
            $table->bigInteger('items_subtotal_amount')->default(0);
            $table->bigInteger('shipping_amount')->default(0);
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('total_amount')->default(0);

            $table->string('status')->default('open');

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index('cart_id');
            // Enforced in the application layer (OpenCheckout), not the DB:
            // "no duplicate *open* checkout per cart" is a status-qualified
            // rule a partial/unique index would need Postgres-specific
            // syntax for, and a cart legitimately accumulates multiple
            // non-open (expired/cancelled) checkouts over time.
            $table->index(['cart_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkouts');
    }
};
