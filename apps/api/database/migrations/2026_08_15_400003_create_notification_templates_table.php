<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('key')->nullable();
            $table->string('name');
            $table->string('channel');
            // Localization-ready structure (spec section 4): one row per
            // locale rather than a jsonb blob of translations, matching
            // this codebase's row-per-variant convention elsewhere
            // (e.g. one NotificationTemplate per channel, not a
            // multi-channel blob). No locale-resolution logic reads this
            // column yet — see docs/architecture/notifications.md.
            $table->string('locale')->default('en');
            $table->string('subject')->nullable();
            $table->text('body_text');
            $table->text('body_html')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('store_id');
            $table->index(['store_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
