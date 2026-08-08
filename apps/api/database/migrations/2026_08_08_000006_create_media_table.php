<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            // entity_type is a closed, application-defined enum (see
            // MediaEntityType), never a raw Eloquent class name — this is
            // intentionally not a classic polymorphic morph column, so a
            // media row can never be pointed at an arbitrary model class.
            $table->string('entity_type');
            $table->ulid('entity_id');

            $table->string('type')->default('image');
            $table->string('disk');
            $table->string('path');
            $table->string('alt')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index('store_id');
            $table->index(['entity_type', 'entity_id']);
            $table->index(['store_id', 'entity_type', 'entity_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
