<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('channel');
            $table->foreignUlid('provider_id')->nullable()->constrained('notification_providers')->nullOnDelete();
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();

            $table->index('store_id');
            $table->unique(['store_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_channels');
    }
};
