<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per granted scope for an InstalledApp — revoked via
     * `revoked_at`, never deleted, so "Permission granted"/"Permission
     * revoked" (spec section 12's audit log) has a durable record beyond
     * the Platform Event. AppTokenAuth middleware checks a scope by
     * `where('scope', $scope)->whereNull('revoked_at')`.
     */
    public function up(): void
    {
        Schema::create('app_permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('installed_app_id')->constrained('installed_apps')->cascadeOnDelete();

            $table->string('scope');
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();

            $table->index('store_id');
            $table->index(['installed_app_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_permissions');
    }
};
