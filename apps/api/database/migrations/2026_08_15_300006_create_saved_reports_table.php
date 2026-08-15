<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A named, reusable report configuration (spec section 6/10:
     * "Saved Reports") — `filters`/`columns` describe what to run, not
     * a result; running it (ad hoc or from a saved config) produces a
     * `Report` row.
     */
    public function up(): void
    {
        Schema::create('saved_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            // orders | products | customers | inventory | shipping | payments | returns | promotions | automation_executions
            $table->string('report_type');
            $table->json('filters')->default('{}');
            $table->json('columns')->default('[]');

            $table->timestamps();

            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
