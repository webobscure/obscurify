<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('vendor')->nullable()->after('description');
            $table->string('product_type')->nullable()->after('vendor');
            $table->string('seo_title')->nullable()->after('status');
            $table->text('seo_description')->nullable()->after('seo_title');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['vendor', 'product_type', 'seo_title', 'seo_description']);
        });
    }
};
