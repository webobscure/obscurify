<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_tags', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');

            // System tags (First Order/Repeat Customer/Inactive/VIP —
            // see AutoTagCustomer) are auto-assigned/removed by
            // RecomputeCustomerMetrics and cannot be deleted by a
            // merchant, unlike every other tag in spec section 3's
            // example list (Wholesale, Employee, Fraud Risk, ...), which
            // stay purely merchant-assigned.
            $table->boolean('is_system')->default(false);

            $table->timestamps();

            $table->index('store_id');
            $table->unique(['store_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tags');
    }
};
