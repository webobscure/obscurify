<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `store_id` nullable: a Private App belongs to the store that
     * created it (installable only there); a Public App has `store_id =
     * null` (platform-level — "internal support only," no marketplace
     * listing/discovery UI exists yet, a merchant is given its install
     * link directly). See docs/architecture/apps.md.
     */
    public function up(): void
    {
        Schema::create('apps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->cascadeOnDelete();

            $table->string('type');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('developer')->nullable();
            $table->text('description')->nullable();
            $table->json('redirect_urls');
            $table->json('requested_scopes');
            $table->string('status')->default('active');

            $table->timestamps();

            $table->index('store_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
