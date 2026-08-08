<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_addresses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('checkout_id')->constrained('checkouts')->cascadeOnDelete();
            $table->string('type');

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();

            $table->timestamps();

            $table->unique(['checkout_id', 'type']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_addresses');
    }
};
