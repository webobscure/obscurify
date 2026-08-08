<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Application\AdjustInventory;
use App\Domain\Inventory\Enums\InventoryMovementReason;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Locations\Models\Location;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->product = app(TenantContext::class)->scope($this->store, fn () => Product::factory()->create());
    $this->variant = app(TenantContext::class)->scope(
        $this->store,
        fn () => ProductVariant::factory()->create(['product_id' => $this->product->id]),
    );
    $this->item = app(TenantContext::class)->scope(
        $this->store,
        fn () => InventoryItem::factory()->create(['product_variant_id' => $this->variant->id]),
    );
    $this->location = app(TenantContext::class)->scope($this->store, fn () => Location::factory()->create());
});

afterEach(function () {
    // Rows here are genuinely committed (no RefreshDatabase in this
    // directory — see Pest.php), so cleanup is manual. Deleting the store
    // cascades to everything tenant-owned (products, variants, inventory
    // items/levels/movements, locations, store_users) via the FKs'
    // ON DELETE CASCADE.
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('does not lose updates across many sequential adjustments to the same row', function () {
    app(TenantContext::class)->scope($this->store, function () {
        foreach (range(1, 20) as $i) {
            app(AdjustInventory::class)->handle($this->item, $this->location, [
                'location_id' => $this->location->id,
                'quantity_delta' => 1,
                'reason' => InventoryMovementReason::ManualAdjustment->value,
            ]);
        }
    });

    $level = InventoryLevel::withoutGlobalScopes()
        ->where('inventory_item_id', $this->item->id)
        ->where('location_id', $this->location->id)
        ->firstOrFail();

    expect($level->on_hand)->toBe(20);
});

it('holds a row lock on the inventory level for the duration of an adjustment transaction', function () {
    app(TenantContext::class)->scope($this->store, fn () => app(AdjustInventory::class)->handle($this->item, $this->location, [
        'location_id' => $this->location->id,
        'quantity_delta' => 5,
        'reason' => InventoryMovementReason::InitialStock->value,
    ]));

    $level = InventoryLevel::withoutGlobalScopes()
        ->where('inventory_item_id', $this->item->id)
        ->where('location_id', $this->location->id)
        ->firstOrFail();

    // A second, independent connection to the same database — simulates a
    // concurrent request trying to adjust the same InventoryLevel row.
    config(['database.connections.pgsql_probe' => config('database.connections.pgsql')]);

    DB::transaction(function () use ($level) {
        // Acquire exactly the lock AdjustInventory takes, and hold it open
        // for the lifetime of this outer transaction.
        DB::table('inventory_levels')->where('id', $level->id)->lockForUpdate()->first();

        $probe = DB::connection('pgsql_probe');

        expect(fn () => $probe->select(
            'select * from inventory_levels where id = ? for update nowait',
            [$level->id],
        ))->toThrow(QueryException::class);
    });

    DB::purge('pgsql_probe');
});
