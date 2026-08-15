<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The variable-picker catalog (spec section 7) — a global, platform-
     * wide table, deliberately not tenant-scoped (BelongsToTenant's
     * global scope always forces a non-null store_id, which would hide
     * shared built-in rows from every store). Seeded once with the
     * built-ins (Customer/Order/Payment/.../Trigger payload fields — see
     * RegisterBuiltInAutomationCatalog). Apps register variables through
     * the existing AppExtension mechanism instead
     * (ExtensionPoint::AutomationVariable — spec section 10: "No core
     * changes required"), which is already correctly tenant-scoped
     * through the installing store; WorkflowVariableRegistry merges both
     * sources at read time — see docs/adr/025-automation-engine.md.
     */
    public function up(): void
    {
        Schema::create('workflow_variables', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // customer | order | payment | shipment | return | inventory | store | trigger
            $table->string('source');
            $table->string('key');
            $table->string('label');
            // string | number | boolean | date | enum | collection
            $table->string('type');
            $table->json('event_types')->nullable();

            $table->timestamps();

            $table->unique(['source', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_variables');
    }
};
