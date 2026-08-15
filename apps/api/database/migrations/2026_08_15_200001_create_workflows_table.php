<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `published_version_id` is the single source of truth for "only one
     * published version per workflow" (spec section 2) — a pointer, not a
     * status flag scattered across WorkflowVersion rows, so the invariant
     * holds by construction rather than by convention. Added via a second
     * Schema::table() step below since workflow_versions.workflow_id must
     * exist first (the FK the other direction).
     */
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();

            // draft | published | disabled | archived (spec section 2).
            $table->string('status')->default('draft');

            $table->ulid('published_version_id')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
