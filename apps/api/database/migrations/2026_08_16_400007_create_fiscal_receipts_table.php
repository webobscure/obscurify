<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec section 5: provider-neutral fiscal receipt architecture. Status
 * is deliberately its own lifecycle, separate from Payment.status (spec
 * section 7/15) — `payment_id` links back to the Payment that triggered
 * this receipt, but a failed fiscalization never touches the Payment or
 * Order's own status. `correction_of_id` self-references the original
 * receipt a future refund/correction receipt corrects (spec section 16
 * — schema-ready, no correction-issuing code yet). `external_receipt_id`
 * mirrors payments' own (provider, external_id) shape for the same
 * reason: idempotent webhook-style callback lookups before any
 * TenantContext is active.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            // Self-referencing FK — added after table creation below,
            // since Postgres can't resolve a foreign key against a table
            // still being created in the same statement.
            $table->ulid('correction_of_id')->nullable();

            $table->string('operation')->default('sale');
            $table->string('status')->default('pending');
            $table->string('provider');
            $table->string('external_receipt_id')->nullable();

            $table->string('seller_inn');
            $table->string('seller_kpp')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();

            $table->char('currency', 3);
            $table->bigInteger('total_amount');

            $table->timestamp('fiscalized_at')->nullable();
            $table->string('error_message')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);

            $table->timestamps();

            $table->index('store_id');
            $table->index('order_id');
            $table->index(['status']);
            $table->unique(['provider', 'external_receipt_id']);
        });

        Schema::table('fiscal_receipts', function (Blueprint $table) {
            $table->foreign('correction_of_id')->references('id')->on('fiscal_receipts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_receipts');
    }
};
