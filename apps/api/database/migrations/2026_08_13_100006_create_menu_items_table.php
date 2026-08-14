<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One entry in a Menu, self-referencing via `parent_id` for genuine
     * nested submenus (mirrors NavigationItem.children in the admin's own
     * sidebar config — a real information-architecture need, not
     * speculative depth). `target_type`/`target_id`/`url` is the same
     * "not true DB polymorphism" pattern WebhookSubscription's
     * `owner_type`/`owner_id` already established (ADR-018): `url` is
     * set only for `target_type = 'url'` (an arbitrary external or
     * hand-typed link); every other target_type stores the referenced
     * row's id in `target_id` and resolves its href at render time (a
     * page/collection/product/blog/blog-post's own slug can change
     * without the menu item needing an update). No FK constraint on
     * `target_id` — it points at a different table depending on
     * `target_type`, so referential integrity for internal targets is
     * enforced in the application layer instead, resolved leniently
     * (a since-deleted target is simply skipped at render time, not a
     * fatal error for the whole menu).
     */
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUlid('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->ulid('parent_id')->nullable();

            $table->string('label');
            $table->string('target_type');
            $table->ulid('target_id')->nullable();
            $table->string('url')->nullable();
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'position']);
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('menu_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
