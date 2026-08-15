<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();

            // manual | dynamic | protected (spec section 2's three kinds).
            // Protected groups are system-seeded (VIP/Inactive/First
            // Order/Repeat Customer — see EnsureProtectedCustomerGroups)
            // and rule-evaluated exactly like dynamic ones; "protected"
            // only blocks delete/rename, it is not a separate membership
            // mechanism — see docs/adr/024-customer-intelligence.md.
            $table->string('type');
            $table->string('status')->default('active');

            $table->timestamps();

            $table->index('store_id');
            $table->unique(['store_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_groups');
    }
};
