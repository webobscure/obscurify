<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A row exists here exactly when the customer is *currently* a
     * computed member of that dynamic/protected CustomerGroup or
     * CustomerSegment — the persisted counterpart of a value
     * SegmentRuleEngine otherwise only ever computes on the fly.
     * RecomputeCustomerMetrics diffs the new evaluation against these
     * rows to detect entered/left transitions (spec section 11) and
     * fire CustomerEnteredSegment/CustomerLeftSegment; a row's mere
     * presence *is* the membership state, so a transition is just an
     * insert (entered) or a delete (left), never an update.
     */
    public function up(): void
    {
        Schema::create('customer_segment_memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('segmentable_type');
            $table->ulid('segmentable_id');
            $table->timestamp('entered_at');

            $table->index('store_id');
            $table->index(['segmentable_type', 'segmentable_id']);
            $table->unique(['customer_id', 'segmentable_type', 'segmentable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_segment_memberships');
    }
};
