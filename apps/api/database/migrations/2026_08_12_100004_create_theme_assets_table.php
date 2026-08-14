<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * File CONTENT never lives in Postgres (spec section 3: "Never store
     * binary files in PostgreSQL" — extended here to every asset type,
     * not just binaries, for one consistent storage mechanism) — `disk`
     * + `path` locate the real file on Laravel's filesystem abstraction
     * (local disk in dev, s3-compatible object storage in production;
     * see config/filesystems.php), this row is metadata only.
     */
    public function up(): void
    {
        Schema::create('theme_assets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('theme_version_id')->constrained('theme_versions')->cascadeOnDelete();

            $table->string('type');
            $table->string('path');
            $table->string('disk')->default('public');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum')->nullable();

            $table->timestamps();

            $table->unique(['theme_version_id', 'path']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_assets');
    }
};
