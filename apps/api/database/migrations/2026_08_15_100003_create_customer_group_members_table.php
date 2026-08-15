<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Membership for *manual* groups (explicit merchant add/remove).
        // Dynamic/protected groups never write rows here — their
        // membership is always computed fresh by SegmentRuleEngine against
        // the group's own SegmentRule tree, never stored.
        Schema::create('customer_group_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('customer_group_id')->constrained('customer_groups')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->timestamp('assigned_at');

            $table->index('store_id');
            $table->index('customer_id');
            $table->unique(['customer_group_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_group_members');
    }
};
