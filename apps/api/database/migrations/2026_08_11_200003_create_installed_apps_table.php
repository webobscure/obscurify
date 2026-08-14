<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (Store, App) install — the anchor every AppToken,
     * AppPermission, AppSetting, and app-owned WebhookSubscription hangs
     * off of. Uninstalling never deletes the row (status -> uninstalled,
     * uninstalled_at set) so tokens/permissions can be revoked and the
     * audit trail (Platform Events) stays intact.
     */
    public function up(): void
    {
        Schema::create('installed_apps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('app_id')->constrained('apps')->cascadeOnDelete();

            $table->string('status')->default('active');
            $table->timestamp('installed_at')->useCurrent();
            $table->timestamp('uninstalled_at')->nullable();

            $table->timestamps();

            $table->unique(['store_id', 'app_id']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installed_apps');
    }
};
