<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            // No `customers` table yet (Foundation/Catalog/Inventory milestones
            // didn't include customer auth) — plain nullable column, no FK,
            // ready for the future customer milestone to adopt.
            $table->ulid('customer_id')->nullable();

            // Opaque, unpredictable guest-cart identifier — see
            // GetOrCreateCart. Global uniqueness is enough: it is never
            // guessed/enumerated, so scoping it by store adds no real
            // security and would only complicate the lookup.
            $table->string('token')->unique();

            $table->char('currency', 3);
            $table->string('status')->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('store_id');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
