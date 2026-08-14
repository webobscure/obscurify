<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('installed_app_id')->constrained('installed_apps')->cascadeOnDelete();

            $table->string('key');
            $table->json('value');

            $table->timestamps();

            $table->unique(['installed_app_id', 'key']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
