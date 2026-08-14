<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A manual URL redirect (301/302) — e.g. an old page slug that moved.
     * `from_path` is unique per store so `RedirectStorefrontRequests`
     * middleware can resolve it with a single indexed lookup on every
     * miss before falling through to a real 404.
     */
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('from_path');
            $table->string('to_path');
            $table->unsignedSmallInteger('status_code')->default(301);

            $table->timestamps();

            $table->unique(['store_id', 'from_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
