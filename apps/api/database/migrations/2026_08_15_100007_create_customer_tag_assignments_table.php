<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_tag_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUlid('customer_tag_id')->constrained('customer_tags')->cascadeOnDelete();
            // 'manual' (a staff user assigned it via the admin UI) or
            // 'system' (RecomputeCustomerMetrics auto-assigned it) — see
            // CustomerTagAssignmentSource.
            $table->string('source');
            $table->timestamp('assigned_at');

            $table->index('store_id');
            $table->unique(['customer_id', 'customer_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tag_assignments');
    }
};
