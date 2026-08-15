<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs the "Update order metadata" automation action (spec section
     * 5) — an open key/value bag, same shape as payments.metadata.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
