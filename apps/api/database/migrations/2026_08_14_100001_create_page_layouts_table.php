<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Builder's structured root for one PageVersion's content (spec
     * Milestone 15 section 1/11: "Builder stores configuration only.
     * ThemeRenderer performs rendering."). 1:1 with a `draft` PageVersion
     * — a PageLayout only ever exists for the version currently being
     * edited; a published version's content is frozen in its own
     * `sections` jsonb column exactly as Milestone 14 left it, never
     * mutated after publish (`PageVersion::assertEditable()` already
     * guards this and needs no change here).
     *
     * SectionInstance/BlockInstance (see their own migrations) are the
     * normalized, per-row form of what PageVersion.sections already
     * stores as one jsonb array — every Builder mutation keeps both
     * representations in sync (see SaveBuilderLayout), so ThemeRenderer
     * — which only ever reads PageVersion.sections — needs zero changes
     * to render Builder-edited content; it cannot tell the difference
     * between a page edited via the Milestone 14 raw-JSON textarea and
     * one edited via the visual Builder.
     */
    public function up(): void
    {
        Schema::create('page_layouts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('page_version_id')->unique()->constrained('page_versions')->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_layouts');
    }
};
