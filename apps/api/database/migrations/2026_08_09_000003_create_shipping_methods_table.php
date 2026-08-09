<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('name');
            $table->string('code');

            $table->string('provider');
            $table->string('service_code')->nullable();

            $table->string('status')->default('active');

            $table->bigInteger('price_amount');
            $table->char('currency', 3);

            $table->unsignedInteger('estimated_days_min')->nullable();
            $table->unsignedInteger('estimated_days_max')->nullable();

            $table->jsonb('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'code']);
            $table->index('store_id');
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
