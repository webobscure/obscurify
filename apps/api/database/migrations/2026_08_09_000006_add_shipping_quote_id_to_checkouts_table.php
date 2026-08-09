<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            $table->foreignUlid('shipping_quote_id')->nullable()->after('cart_id')
                ->constrained('shipping_quotes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_quote_id');
        });
    }
};
