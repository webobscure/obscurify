<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('push_enabled')->default(false);
            $table->boolean('marketing_opt_in')->default(false);
            $table->boolean('transactional_only')->default(true);
            // Quiet hours: structure only (spec section 6) — stored, never
            // enforced by the delivery engine this milestone; see
            // docs/architecture/notifications.md.
            $table->string('quiet_hours_start')->nullable();
            $table->string('quiet_hours_end')->nullable();
            $table->string('quiet_hours_timezone')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->unique(['store_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
