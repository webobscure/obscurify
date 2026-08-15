<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->boolean('is_default_billing')->default(false)->after('address_line2');
            $table->boolean('is_default_shipping')->default(false)->after('is_default_billing');
        });
    }

    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropColumn(['is_default_billing', 'is_default_shipping']);
        });
    }
};
