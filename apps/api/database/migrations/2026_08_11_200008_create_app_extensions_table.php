<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per extension point an InstalledApp contributes to (spec
     * section 8/9: Checkout/Order/Product/Customer Extension, Admin
     * Navigation, Admin Widgets, Dashboard Cards) — see
     * ExtensionPointRegistry for the fixed, pluggable set of valid
     * `extension_point` values and each one's `config` shape.
     */
    public function up(): void
    {
        Schema::create('app_extensions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('installed_app_id')->constrained('installed_apps')->cascadeOnDelete();

            $table->string('extension_point');
            $table->json('config');
            $table->string('status')->default('active');

            $table->timestamps();

            $table->index('store_id');
            $table->index(['installed_app_id', 'extension_point']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_extensions');
    }
};
