<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->string('channel');
            $table->string('event_type')->nullable();
            $table->string('subject')->nullable();
            $table->text('body_text');
            $table->text('body_html')->nullable();
            $table->string('related_type')->nullable();
            $table->ulid('related_id')->nullable();
            $table->foreignUlid('workflow_execution_id')->nullable()->constrained('workflow_executions')->nullOnDelete();
            $table->string('triggered_by');
            $table->string('status')->default('pending');

            $table->timestamps();

            $table->index('store_id');
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
