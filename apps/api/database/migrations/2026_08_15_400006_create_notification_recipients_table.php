<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_recipients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->string('recipient_type');
            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            // Ad-hoc target (an email/phone the customer_id doesn't
            // resolve, or a per-delivery override) — nullable because a
            // webhook-channel recipient has no address at all (spec: the
            // provider itself owns where a webhook goes).
            $table->string('address')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index(['store_id', 'notification_id']);
            $table->index(['store_id', 'customer_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_recipients');
    }
};
