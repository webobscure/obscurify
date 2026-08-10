<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One inspection per ReturnItem (unique constraint) — re-inspection is
     * not supported this milestone, matching "do not build more than the
     * spec asks for." Write-once record (no updated_at): once a merchant
     * has physically inspected an item, that assessment doesn't change
     * retroactively; a mistaken inspection is a manual-review/support
     * matter, not a data-correction feature this milestone builds.
     * `photos` is free-form metadata (paths/URLs) — no file storage
     * pipeline is part of this milestone, only the column to hold
     * references for whenever one exists.
     */
    public function up(): void
    {
        Schema::create('return_inspections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('return_item_id')->constrained('return_items')->cascadeOnDelete();

            $table->string('condition');
            $table->jsonb('photos')->nullable();
            $table->text('notes')->nullable();

            $table->foreignUlid('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at');
            $table->timestamp('created_at');

            $table->index('store_id');
            $table->unique('return_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_inspections');
    }
};
