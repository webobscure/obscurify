<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Always dynamic (rule-computed) — unlike CustomerGroup, a
        // CustomerSegment never has manual membership. Its rule tree can
        // nest AND/OR groups (spec section 5), unlike CustomerGroup's
        // flat, implicitly-ANDed rule list — both are stored as
        // SegmentRule rows via the same polymorphic `segmentable`
        // relation, evaluated by the same SegmentRuleEngine.
        Schema::create('customer_segments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('store_id');
            $table->unique(['store_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_segments');
    }
};
