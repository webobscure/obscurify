<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One disposition decision per ReturnItem (unique constraint), chosen
     * alongside inspection (InspectReturn) but only *applied* to Inventory
     * later, at CompleteReturn — `applied_at` marks that moment, kept
     * nullable/mutable (unlike ReturnInspection) specifically so
     * CompleteReturn can stamp it once the InventoryMovement it triggers
     * has actually been written, under the same row lock.
     */
    public function up(): void
    {
        Schema::create('return_dispositions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('return_item_id')->constrained('return_items')->cascadeOnDelete();

            $table->string('disposition');
            $table->text('notes')->nullable();

            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at');
            $table->timestamp('applied_at')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->unique('return_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_dispositions');
    }
};
