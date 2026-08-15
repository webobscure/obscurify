<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One table, two node kinds, distinguished by which columns are
     * populated (spec section 5: AND/OR, nested groups, comparison/date/
     * numeric/string/boolean operators):
     *
     *  - A *condition* node: `field`/`operator`/`value` set, `boolean_operator`
     *    null, no children — one leaf test (e.g. "total_spent > 500").
     *  - A *group* node: `boolean_operator` set (and/or), `field`/`operator`/
     *    `value` null, has children (other SegmentRule rows with
     *    `parent_id` pointing at it) — combines its children with that
     *    operator. Nesting is just a group node whose child is itself a
     *    group node.
     *
     * `segmentable_type`/`segmentable_id` is a manual polymorphic pair
     * (not Eloquent's built-in morphTo helper, so the two allowed values
     * stay explicit strings — 'CustomerGroup' | 'CustomerSegment', not a
     * class-map-dependent alias) — both a dynamic/protected CustomerGroup
     * and a CustomerSegment own their own independent rule tree through
     * the same table and the same SegmentRuleEngine.
     */
    public function up(): void
    {
        Schema::create('segment_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('segmentable_type');
            $table->ulid('segmentable_id');
            $table->ulid('parent_id')->nullable();

            $table->string('boolean_operator')->nullable();
            $table->string('field')->nullable();
            $table->string('operator')->nullable();
            $table->json('value')->nullable();

            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('store_id');
            $table->index(['segmentable_type', 'segmentable_id']);
            $table->index('parent_id');
        });

        // Self-referencing FK: Postgres can't validate it against a
        // not-yet-committed primary key inside the same Schema::create()
        // (see customer_access_tokens migration for the identical
        // precedent, itself following app_tokens).
        Schema::table('segment_rules', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('segment_rules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segment_rules');
    }
};
