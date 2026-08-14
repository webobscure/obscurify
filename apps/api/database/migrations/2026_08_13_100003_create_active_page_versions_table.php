<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per page — mirrors ActiveTheme exactly (see that
     * migration's docblock), scoped per-page instead of per-store since
     * many pages are live simultaneously (unlike themes, where only one
     * is active for the whole store). Points at a specific *version*, so
     * rollback is simply repointing `page_version_id` at an older
     * published version — the same O(1) rollback ADR-019 established.
     */
    public function up(): void
    {
        Schema::create('active_page_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('page_id')->unique()->constrained('pages')->cascadeOnDelete();
            $table->foreignUlid('page_version_id')->constrained('page_versions')->cascadeOnDelete();

            $table->timestamp('activated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_page_versions');
    }
};
