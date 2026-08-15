<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs the "Update customer metadata" automation action (spec
     * section 5) — an open key/value bag a workflow can write into,
     * distinct from CustomerPreference (which is customer-authored) and
     * CustomerMetric (which is always recomputed, never written to
     * directly).
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->json('metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
