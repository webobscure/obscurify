<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            $table->foreignUlid('discount_code_id')->nullable()->after('shipping_quote_id')
                ->constrained('discount_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_code_id');
        });
    }
};
