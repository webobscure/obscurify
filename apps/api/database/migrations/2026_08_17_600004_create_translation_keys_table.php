<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_keys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('namespace_id')->constrained('translation_namespaces')->cascadeOnDelete();
            $table->string('key');
            $table->string('description')->nullable();

            $table->timestamps();

            $table->unique(['namespace_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_keys');
    }
};
