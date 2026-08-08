<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('barcode')->nullable()->after('sku');
            $table->bigInteger('compare_at_price_amount')->nullable()->after('price_amount');
            $table->bigInteger('cost_amount')->nullable()->after('compare_at_price_amount');
            $table->decimal('weight', 10, 3)->nullable()->after('currency');
            $table->decimal('length', 10, 3)->nullable()->after('weight');
            $table->decimal('width', 10, 3)->nullable()->after('length');
            $table->decimal('height', 10, 3)->nullable()->after('width');

            // Sorted, comma-joined product_option_value ids selected for this
            // variant. A DB-level safety net (on top of the application-level
            // check in CreateVariant/UpdateVariant) so two variants of the
            // same product can never share the same option combination, even
            // under concurrent requests. Empty string for option-less
            // products, which in turn means only one such "default" variant
            // is allowed per product.
            $table->string('option_signature')->default('')->after('height');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique(['product_id', 'option_signature']);
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'option_signature']);
            $table->dropColumn([
                'barcode',
                'compare_at_price_amount',
                'cost_amount',
                'weight',
                'length',
                'width',
                'height',
                'option_signature',
            ]);
        });
    }
};
