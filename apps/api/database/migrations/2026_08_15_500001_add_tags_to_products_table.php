<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No tagging feature existed on Product before Milestone 22 — added
     * here because Search's own spec explicitly requires indexing and
     * faceting on product tags (spec sections 3/6), and a search index
     * has nothing real to read without a source column for it. Free-form
     * strings, not a separate catalog table (unlike CustomerTag, which
     * needed store-wide rename/merge semantics) — a product tag here is
     * just an unstructured label used only for search/facet purposes.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('product_type');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
};
