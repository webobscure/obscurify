<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One placed block within a SectionInstance — the relational
     * counterpart of one entry in a section instance's `blocks` array.
     * `parent_block_instance_id` is self-referencing so a block can
     * nest inside another block (spec section 2: "Nested blocks" — e.g.
     * an Accordion or Tabs block containing child blocks), the same
     * adjacency-list pattern MenuItem already established for nested
     * navigation. A top-level block within its section has
     * `parent_block_instance_id = null`.
     */
    public function up(): void
    {
        Schema::create('block_instances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('section_instance_id')->constrained('section_instances')->cascadeOnDelete();
            $table->ulid('parent_block_instance_id')->nullable();

            $table->string('block_handle');
            $table->unsignedInteger('position')->default(0);
            $table->json('settings');

            $table->timestamps();

            $table->index(['section_instance_id', 'parent_block_instance_id', 'position']);
        });

        Schema::table('block_instances', function (Blueprint $table) {
            $table->foreign('parent_block_instance_id')->references('id')->on('block_instances')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_instances');
    }
};
