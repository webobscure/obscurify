<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A CMS page "product" a store owns — mirrors Theme exactly (see
     * that migration's docblock and docs/architecture/cms.md): the
     * actual editable/renderable content lives on its PageVersion rows,
     * this table only carries identity and lifecycle status.
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('title');
            $table->string('slug');
            $table->string('status')->default('draft');

            $table->timestamps();

            $table->unique(['store_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
